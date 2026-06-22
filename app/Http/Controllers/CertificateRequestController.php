<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\CA\GoCAService;
use App\Services\AuditService;

class CertificateRequestController extends Controller
{
    /**
     * 🔥 LIST PENDING REQUESTS
     */
    public function index()
    {
        // Kein Audit — read-only Listenabfrage
        $requests = Certificate::where('status', 'pending')
            ->latest()
            ->get();

        return response()->json(
            $requests->map(function ($cert) {

                return [
                    'id' => $cert->id,
                    'common_name' => $cert->common_name,
                    'type' => $cert->type,
                    'san' => $cert->san,
                    'status' => $cert->status,

                    'key_type' => $cert->key_type,
                    'key_size' => $cert->key_size,
                    'curve' => $cert->curve,

                    'requested_by' => $cert->requested_by,
                    'created_at' => $cert->created_at,
                ];
            })
        );
    }

    /**
     * 🔥 APPROVE REQUEST
     */
    public function approve($id, GoCAService $ca)
    {
        $user = Auth::user();

        if (!$user?->hasPermission('certificate.approve')) {
            return response()->json([
                'error_key' => 'backend.auth.permission_denied'
            ], 403);
        }

        $cert = Certificate::findOrFail($id);

        if ($cert->status !== 'pending') {
            return response()->json([
                'error_key' => 'backend.certificate_request.not_pending'
            ], 400);
        }

        /*
         * request_data enthält die ursprünglichen Antragsdaten.
         *
         * Für neue Requests:
         * - method = data
         * - method = csr
         *
         * Für alte Pending Requests ohne request_data gibt es einen Fallback.
         */
        $requestData = $cert->request_data;

        if (!is_array($requestData)) {
            $requestData = [];
        }

        // 🔥 Fallback für alte Pending Requests ohne request_data
        if (empty($requestData)) {
            $sanDns = [];

            if ($cert->san) {
                $decoded = json_decode($cert->san, true);

                if (is_array($decoded)) {
                    $sanDns = $decoded;
                }
            }

            $requestData = [
                'method' => 'data',

                'type' => $cert->type,
                'common_name' => $cert->common_name,

                'organization' => null,
                'ou' => null,
                'locality' => null,
                'state' => null,
                'country' => 'DE',
                'email' => null,

                'san_dns' => $sanDns,
                'san_ips' => [],

                'key_type' => $cert->key_type,
                'key_size' => $cert->key_size,
                'curve' => $cert->curve,

                'parent_id' => $cert->parent_id,
            ];
        }

        // 🔥 Intermediate ermitteln
        if (!empty($requestData['parent_id'])) {
            $parent = Certificate::find($requestData['parent_id']);
        } else {
            $parent = Certificate::where('type', 'intermediate')
                ->latest()
                ->first();
        }

        if (!$parent) {
            return response()->json([
                'error_key' => 'backend.certificate.no_intermediate'
            ], 400);
        }

        $intermediateId = basename(dirname($parent->crt_path));

        /*
         * =====================================================
         * 🔥 CSR REQUEST
         * =====================================================
         */
        if (($requestData['method'] ?? 'data') === 'csr') {
            if (empty($requestData['csr'])) {
                return response()->json([
                    'error_key' => 'backend.certificate_request.csr_missing'
                ], 400);
            }

            $result = $ca->signCsr(
                $requestData['csr'],
                $intermediateId,
                $requestData['type'] ?? $cert->type
            );
        }

        /*
         * =====================================================
         * 🔥 DATA REQUEST
         * =====================================================
         */ else {
            $payload = [
                'type' => $requestData['type'],
                'common_name' => $requestData['common_name'],

                'organization' => $requestData['organization'] ?? null,
                'ou' => $requestData['ou'] ?? null,
                'locality' => $requestData['locality'] ?? null,
                'state' => $requestData['state'] ?? null,
                'country' => $requestData['country'] ?? 'DE',
                'email' => $requestData['email'] ?? null,

                'san_dns' => $requestData['san_dns'] ?? [],
                'san_ips' => $requestData['san_ips'] ?? [],

                'key_type' => $requestData['key_type'],

                'key_size' => ($requestData['key_type'] ?? null) === 'rsa'
                    ? ($requestData['key_size'] ?? null)
                    : null,

                'curve' => ($requestData['key_type'] ?? null) === 'ecdsa'
                    ? ($requestData['curve'] ?? null)
                    : null,

                'intermediate' => $intermediateId,
            ];

            $result = $ca->signFromData($payload);
        }

        // 🔥 bestehenden Pending Request aktualisieren
        $cert->update([
            'status' => 'issued',

            'approved_by' => $user->id,
            'approved_at' => now(),

            'parent_id' => $parent->id,

            'serial_number' => $result['serial_number'],

            'valid_from' => $result['valid_from'],
            'valid_to' => $result['valid_to'],

            'crt_path' => $result['crt_path'],
            'key_path' => $result['key_path'] ?? null,
            'chain_path' => $result['chain_path'],
        ]);

        AuditService::log(AuditService::CERT_APPROVED, $cert, [
            'common_name'   => $cert->common_name,
            'serial_number' => $result['serial_number'],
            'method'        => $requestData['method'] ?? 'data',
        ]);

        return response()->json([
            'message_key' => 'backend.certificate_request.approved',
            'certificate' => $cert->fresh(),
        ]);
    }

    /**
     * 🔥 REJECT REQUEST
     */
    public function reject(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user?->hasPermission('certificate.approve')) {
            return response()->json([
                'error_key' => 'backend.auth.permission_denied'
            ], 403);
        }

        $cert = Certificate::findOrFail($id);

        if ($cert->status !== 'pending') {
            return response()->json([
                'error_key' => 'backend.certificate_request.not_pending'
            ], 400);
        }

        $cert->update([
            'status' => 'rejected',

            'rejected_by' => $user->id,
            'rejected_at' => now(),

            'rejection_reason' => $request->reason,
        ]);

        AuditService::log(AuditService::CERT_REJECTED, $cert, [
            'common_name'      => $cert->common_name,
            'rejection_reason' => $request->reason,
        ]);

        return response()->json([
            'message_key' => 'backend.certificate_request.rejected',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CA\GoCAService;
use App\Models\Certificate;
use Carbon\Carbon;
use App\Services\Notifications\NotificationEngine;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Setting;




class CertificateController extends Controller
{
    public function create(Request $request, GoCAService $ca)
    {
        $request->validate([
            'csr' => 'required|file',
            'type' => 'required|in:tls,client,codesign',
            'intermediate' => 'nullable|integer',
            'team_id'     => ['nullable', 'integer', Rule::exists('teams', 'id')],
        ]);

        /** @var User|null $user */
        $user = Auth::user();

        $canRequest = $user?->hasPermission('certificate.request');
        $canCreate = $user?->hasPermission('certificate.create');

        if (!$canRequest && !$canCreate) {
            return response()->json([
                'error_key' => 'backend.auth.permission_denied'
            ], 403);
        }

        $csrContent = file_get_contents(
            $request->file('csr')->getRealPath()
        );

        $subject = openssl_csr_get_subject($csrContent, true);

        if (!$subject) {
            return response()->json([
                'error_key' => 'backend.certificate.invalid_csr'
            ], 400);
        }

        $commonName = $subject['CN'] ?? $subject['commonName'] ?? null;

        if (!$commonName) {
            return response()->json([
                'error_key' => 'backend.certificate.csr_missing_cn'
            ], 400);
        }
        $type = $request->input('type', 'tls');

        // =====================================================
        // 🔥 REQUEST ONLY
        // User darf CSR beantragen, aber nicht direkt signieren
        // =====================================================

        if (!$canCreate) {
            $requestPayload = [
                'method' => 'csr',
                'type' => $type,
                'common_name' => $commonName,
                'csr' => $csrContent,
                'parent_id' => $request->intermediate,
            ];

            $cert = Certificate::create([
                'type' => $type,
                'common_name' => $commonName,
                'san' => null,

                'status' => 'pending',
                'requested_by' => $user?->id,

                'parent_id' => $request->intermediate,

                'request_data' => $requestPayload,
                'team_id'      => $request->input('team_id')
            ]);

            AuditService::log(AuditService::CERT_REQUESTED, $cert, [
                'common_name' => $commonName,
                'type'        => $type,
                'method'      => 'csr',
            ]);

            return response()->json([
                'message_key' => 'backend.certificate.csr_request_submitted',
                'certificate' => $cert,
            ]);
        }

        // =====================================================
        // 🔥 DIRECT ISSUE
        // User darf direkt aus CSR signieren
        // =====================================================

        if ($request->intermediate) {
            $parent = Certificate::findOrFail($request->intermediate);
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

        $data = $ca->signCsr($csrContent, $intermediateId, $type);

        $cert = Certificate::create([
            'type' => $data['type'],
            'common_name' => $data['common_name'],
            'san' => !empty($data['san']) ? json_encode($data['san']) : null,

            'status' => 'issued',
            'approved_by' => $user?->id,
            'approved_at' => now(),

            'serial_number' => $data['serial_number'],
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'],

            'crt_path' => $data['crt_path'],
            'key_path' => $data['key_path'] ?? null,
            'chain_path' => $data['chain_path'],

            'parent_id' => $parent->id,
            'team_id'   => $request->input('team_id'),
        ]);

        AuditService::log(AuditService::CERT_ISSUED, $cert, [
            'common_name'   => $cert->common_name,
            'serial_number' => $cert->serial_number,
            'type'          => $cert->type,
            'method'        => 'csr',
        ]);

        app(NotificationEngine::class)->dispatch('certificate_created', [
            'common_name' => $cert->common_name,
            'serial' => $cert->serial_number,
            'email' => $request->email ?? null,
            'download' => url("/api/certificates/{$cert->id}/download?type=crt"),
        ]);

        return response()->json($cert);
    }

    public function revoke(int $id, GoCAService $ca)
    {
        $cert = Certificate::findOrFail($id);

        // ❌ Sicherheit: keine CA revoken
        if (in_array($cert->type, ['root', 'intermediate'])) {
            return response()->json([
                'error_key' => 'backend.certificate.cannot_revoke_ca'
            ], 400);
        }

        // ❌ Bereits revoked verhindern
        if ($cert->revoked) {
            return response()->json([
                'error_key' => 'backend.certificate.already_revoked'
            ], 400);
        }

        // 🔐 erlaubte Gründe (PKI Standard)
        $allowedReasons = [
            'unspecified',
            'key_compromise',
            'cessation_of_operation',
            'superseded'
        ];

        $reason = request('reason', 'unspecified');

        if (!in_array($reason, $allowedReasons)) {
            return response()->json([
                'error_key' => 'backend.certificate.invalid_revocation_reason'
            ], 400);
        }

        // 🔥 Revocation setzen
        $cert->revoked = true;
        $cert->revoked_at = now();
        $cert->revocation_reason = $reason;
        $cert->save();

        AuditService::log(AuditService::CERT_REVOKED, $cert, [
            'common_name'   => $cert->common_name,
            'serial_number' => $cert->serial_number,
            'reason'        => $reason,
        ]);

        app(NotificationEngine::class)->dispatch('certificate_revoked', [
            'common_name' => $cert->common_name,
            'serial' => $cert->serial_number,
            'email' => null, // 🔥 oder besser:
            'revocation_reason' => $cert->revocation_reason,
            'download' => url("/api/certificates/{$cert->id}/download?type=crt"),
        ]);

        // 🔥 OCSP CACHE INVALIDATION
        try {
            $ca->clearOcspCache();
        } catch (\Exception $e) {
            // optional loggen
        }


        return response()->json([
            'message_key' => 'backend.certificate.revoked',
            'certificate' => [
                'id' => $cert->id,
                'revoked' => true,
                'revoked_at' => $cert->revoked_at,
                'reason' => $cert->revocation_reason
            ]
        ]);
    }

    public function revokedList(Request $request)
    {
        $query = Certificate::where('revoked', true);

        if ($request->intermediate) {
            $id = (int) str_replace('int-', '', $request->intermediate);

            $query->where('parent_id', $id);
        }

        return $query->get()->map(function ($cert) {
            return [
                'serial_number' => $cert->serial_number,
                'revoked_at' => Carbon::parse($cert->revoked_at)->format('Y-m-d H:i:s'),
                'reason' => $cert->revocation_reason ?? 'unspecified'
            ];
        });
    }


    public function createFromData(Request $request, GoCAService $ca)
    {
        // 🔥 1. VALIDATION (JETZT RICHTIG)
        $request->validate([
            'type' => 'required|in:tls,client,codesign',

            // 🔥 cn ODER common_name erlauben
            'cn' => 'nullable|string',
            'common_name' => 'nullable|string',

            'organization' => 'nullable|string',
            'ou' => 'nullable|string',
            'locality' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'required|string',
            'email' => 'nullable|string',

            'san_dns' => 'nullable|array',
            'san_ips' => 'nullable|array',

            'key_type' => 'required|in:rsa,ecdsa',
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],

            'key_size' => 'required_if:key_type,rsa|nullable|in:2048,3072,4096',
            'curve' => 'required_if:key_type,ecdsa|nullable|in:P256,P384,P521',
            'parent_id' => 'nullable|integer',
        ]);


        /** @var User|null $user */
        $user = Auth::user();

        $canRequest = $user?->hasPermission('certificate.request');
        $canCreate = $user?->hasPermission('certificate.create');

        // =====================================================
        // ❌ GAR KEIN RECHT
        // =====================================================

        if (!$canRequest && !$canCreate) {
            return response()->json([
                'message_key' => 'backend.certificate.not_allowed'
            ], 403);
        }

        // =====================================================
        // 🔥 SECURITY POLICY CHECKS
        // =====================================================

        // 🔹 SAN Pflichtfeld NUR für TLS-Zertifikate
        if (
            $request->type === 'tls' &&
            Setting::getValue('require_san') === 'true'
        ) {
            $sanDns = array_filter($request->san_dns ?? []);
            $sanIps = array_filter($request->san_ips ?? []);

            if (empty($sanDns) && empty($sanIps)) {
                return response()->json([
                    'error_key' => 'backend.certificate.san_required'
                ], 422);
            }
        }

        // 🔹 E-Mail Pflichtfeld
        if (Setting::getValue('require_email') === 'true') {
            if (empty(trim($request->email ?? ''))) {
                return response()->json([
                    'error_key' => 'backend.certificate.email_required'
                ], 422);
            }
        }

        // 🔹 Wildcard prüfen NUR für TLS-Zertifikate
        if (
            $request->type === 'tls' &&
            Setting::getValue('allow_wildcards') === 'false'
        ) {
            $sanDns = array_filter($request->san_dns ?? []);

            foreach ($sanDns as $dns) {
                if (str_starts_with($dns, '*.')) {
                    return response()->json([
                        'error_key' => 'backend.certificate.wildcards_not_allowed'
                    ], 422);
                }
            }
        }


        $requestPayload = [
            'type' => $request->type,
            'common_name' => $request->cn,

            'organization' => $request->organization,
            'ou' => $request->ou,
            'locality' => $request->locality,
            'state' => $request->state,
            'country' => $request->country,
            'email' => $request->email,

            'san_dns' => $request->san_dns ?? [],
            'san_ips' => $request->san_ips ?? [],

            'key_type' => $request->key_type,

            'key_size' => $request->key_type === 'rsa'
                ? $request->key_size
                : null,

            'curve' => $request->key_type === 'ecdsa'
                ? $request->curve
                : null,

            'parent_id' => $request->parent_id,
        ];


        // =====================================================
        // 🔥 REQUEST ONLY
        // User darf beantragen, aber nicht direkt erstellen
        // =====================================================

        if (!$canCreate) {

            $cert = Certificate::create([
                'type' => $request->type,
                'common_name' => $request->cn,
                'san' => !empty($request->san_dns)
                    ? json_encode($request->san_dns)
                    : null,

                'status' => 'pending',
                'requested_by' => $user?->id,

                'parent_id' => $request->parent_id,

                'key_type' => $request->key_type,
                'key_size' => $request->key_type === 'rsa'
                    ? $request->key_size
                    : null,

                'curve' => $request->key_type === 'ecdsa'
                    ? $request->curve
                    : null,

                'request_data' => $requestPayload,
                'team_id'      => $request->input('team_id'),
            ]);

            AuditService::log(AuditService::CERT_REQUESTED, $cert, [
                'common_name' => $request->cn,
                'type'        => $request->type,
                'method'      => 'form',
            ]);

            return response()->json([
                'message_key' => 'backend.certificate.request_submitted',
                'certificate' => $cert,
            ]);
        }

        // =====================================================
        // 🔥 DIRECT ISSUE (ADMIN / PKIADMIN)
        // =====================================================

        // 🔥 2. INTERMEDIATE ERMITTELN
        if ($request->parent_id) {
            $parent = Certificate::findOrFail($request->parent_id);
        } else {
            $parent = Certificate::where('type', 'intermediate')
                ->latest()
                ->first();
        }

        if (!$parent) {
            return response()->json(['error_key' => 'backend.certificate.no_intermediate'], 400);
        }

        // 🔥 WICHTIG → int-1 extrahieren
        $intermediateId = basename(dirname($parent->crt_path));

        // 🔥 3. DATA MAPPING → GO FORMAT
        $payload = [
            'type' => $request->type,
            'common_name' => $request->cn,
            'organization' => $request->organization,
            'ou' => $request->ou,
            'locality' => $request->locality,
            'state' => $request->state,
            'country' => $request->country,
            'email' => $request->email,
            'san_dns' => $request->san_dns ?? [],
            'san_ips' => $request->san_ips ?? [],
            'key_type' => $request->key_type,
            'key_size' => $request->key_type === 'rsa' ? $request->key_size : null,
            'curve' => $request->key_type === 'ecdsa' ? $request->curve : null,
            'intermediate' => $intermediateId,
        ];

        // 🔥 4. GO CALL
        $result = $ca->signFromData($payload);

        // 🔥 5. DB SAVE
        $cert = Certificate::create([
            'type' => $result['type'],
            'common_name' => $result['common_name'],
            'san' => $result['san'] ? json_encode($result['san']) : null,
            'status' => 'issued',
            'approved_by' => $user?->id,
            'approved_at' => now(),
            'serial_number' => $result['serial_number'],
            'valid_from' => $result['valid_from'],
            'valid_to' => $result['valid_to'],
            'crt_path' => $result['crt_path'],
            'key_path' => $result['key_path'],
            'chain_path' => $result['chain_path'],
            'parent_id' => $parent->id,
            'key_type' => $request->key_type,
            'key_size' => $request->key_type === 'rsa' ? $request->key_size : null,
            'curve' => $request->key_type === 'ecdsa' ? $request->curve : null,
            'team_id' => $request->input('team_id'),

        ]);

        AuditService::log(AuditService::CERT_ISSUED, $cert, [
            'common_name'   => $cert->common_name,
            'serial_number' => $cert->serial_number,
            'type'          => $cert->type,
            'method'        => 'form',
        ]);

        app(NotificationEngine::class)->dispatch('certificate_created', [
            'common_name' => $cert->common_name,
            'serial' => $cert->serial_number,
            'email' => $request->email ?? null,
            'download' => url("/api/certificates/{$cert->id}/download?type=crt"),
        ]);


        return response()->json($cert);
    }

    public function assignTeam(Request $request, int $id)
    {
        $cert = Certificate::findOrFail($id);

        $validated = $request->validate([
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
        ]);

        $oldTeamId = $cert->team_id;
        $cert->team_id = $validated['team_id'];
        $cert->save();

        AuditService::log('certificate.team.assigned', $cert, [
            'common_name' => $cert->common_name,
            'old_team_id' => $oldTeamId,
            'new_team_id' => $cert->team_id,
        ]);

        return response()->json(['success' => true]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Services\AuditService;
use App\Services\Notifications\NotificationEngine;

class InternalACMECAController extends Controller
{
    public function acmeSettings()
    {
        $intermediateId = Setting::getValue('active_intermediate');
        $crlBase = Setting::getValue('crl_base_url');
        $ocspBase = Setting::getValue('ocsp_base_url');
        $validityDays = (int) (Setting::getValue('max_validity_days') ?? 90);
        $dnsServers = json_decode(Setting::getValue('dns_servers') ?? '[]', true);

        return response()->json([
            'intermediate_id' => $intermediateId,
            'crl_url' => rtrim($crlBase, '/') . '/' . $intermediateId . '.pem',
            'ocsp_url' => rtrim($ocspBase, '/'),
            'validity_days' => $validityDays,
            'dns_servers' => $dnsServers,
        ]);
    }

    public function storeCertificate(Request $request)
    {
        $request->validate([
            'common_name' => 'required|string',
            'serial_number' => 'required|string',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date',
            'crt_path' => 'required|string',
        ]);

        // parent_id automatisch ermitteln wenn nicht angegeben
        $parentId = $request->parent_id;
        if (!$parentId) {
            $intermediateId = Setting::getValue('active_intermediate');
            $parent = Certificate::where('type', 'intermediate')
                ->whereRaw("crt_path LIKE ?", ['%/' . $intermediateId . '/%'])
                ->first();
            $parentId = $parent?->id;
        }

        $cert = Certificate::create([
            'type' => 'tls',
            'common_name' => $request->common_name,
            'san' => json_encode($request->san ?? []),
            'serial_number' => $request->serial_number,
            'valid_from' => $request->valid_from,
            'valid_to' => $request->valid_to,
            'crt_path' => $request->crt_path,
            'status' => 'issued',
            'is_acme' => true,
            'parent_id' => $parentId,
            'acme_account_id' => $request->acme_account_id,
        ]);

        app(NotificationEngine::class)->dispatch('acme_certificate_issued', [
            'common_name' => $cert->common_name,
            'serial' => $cert->serial_number,
            'valid_from' => $cert->valid_from,
            'valid_to' => $cert->valid_to,
            'acme_account_id' => $cert->acme_account_id,
        ]);

        AuditService::log(AuditService::ACME_CERT_ISSUED, $cert, [
            'common_name' => $cert->common_name,
            'serial_number' => $cert->serial_number,
            'acme_account_id' => $cert->acme_account_id,
        ]);

        return response()->json(['id' => $cert->id]);
    }

    public function acmeAccounts()
    {
        $response = \App\Services\CA\GoCAService::client()
            ->timeout(5)
            ->get(config('services.ca.url') . '/acme/accounts');

        return response()->json($response->json());
    }

    public function acmeAccountDomains()
    {
        $domains = Certificate::where('is_acme', true)
            ->whereNotNull('acme_account_id')
            ->select('acme_account_id', 'common_name')
            ->get()
            ->groupBy('acme_account_id')
            ->map(fn($certs) => $certs->pluck('common_name')->unique()->values());

        return response()->json($domains);
    }

    public function revokeByCertificate(Request $request)
    {
        $request->validate([
            'serial' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        $cert = Certificate::where('serial_number', $request->serial)
            ->where('is_acme', true)
            ->first();

        if (!$cert) {
            return response()->json(['error' => 'certificate not found'], 404);
        }

        if ($cert->revoked) {
            return response()->json(['error' => 'already revoked'], 400);
        }

        $allowedReasons = ['unspecified', 'key_compromise', 'cessation_of_operation', 'superseded'];
        $reason = $request->reason ?? 'unspecified';
        if (!in_array($reason, $allowedReasons)) {
            $reason = 'unspecified';
        }

        $cert->revoked = true;
        $cert->revoked_at = now();
        $cert->revocation_reason = $reason;
        $cert->save();

        app(NotificationEngine::class)->dispatch('acme_certificate_revoked', [
            'common_name' => $cert->common_name,
            'serial' => $cert->serial_number,
            'reason' => $cert->revocation_reason,
        ]);

        AuditService::log(AuditService::ACME_CERT_REVOKED, $cert, [
            'common_name' => $cert->common_name,
            'serial_number' => $cert->serial_number,
            'reason' => $cert->revocation_reason,
        ]);

        // OCSP Cache leeren
        try {
            app(\App\Services\CA\GoCAService::class)->clearOcspCache();
        } catch (\Exception $e) {
        }

        return response()->json(['success' => true]);
    }

    public function deactivateAccount(string $accountId)
    {
        $response = \App\Services\CA\GoCAService::client()
            ->timeout(5)
            ->post(config('services.ca.url') . '/acme/account/deactivate/' . $accountId);

        if (!$response->successful()) {
            return response()->json(['error' => 'deactivation failed'], 500);
        }

        app(NotificationEngine::class)->dispatch('acme_account_deactivated', [
            'account_id' => $accountId,
        ]);

        AuditService::log(AuditService::ACME_ACCOUNT_DEACTIVATED, null, [
            'account_id' => $accountId,
        ]);

        return response()->json(['success' => true]);
    }
}

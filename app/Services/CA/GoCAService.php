<?php

namespace App\Services\CA;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use App\Models\Certificate;
use App\Models\Setting;

class GoCAService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ca.url');
    }

    // 🔥 Statischer HTTP Client für alle Controller
    public static function client(): \Illuminate\Http\Client\PendingRequest
    {
        return \Illuminate\Support\Facades\Http::withHeaders([
            'X-CA-Token' => config('services.ca.token'),
        ]);
    }



    // =========================================
    // 🔥 HTTP Client mit Shared Secret
    // =========================================

    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'X-CA-Token' => config('services.ca.token'),
        ]);
    }

    /**
     * 🔥 Holt das passende Intermediate Model
     */
    private function resolveIntermediate(null|string|int $intermediateId = null): array
    {
        if (!$intermediateId) {
            $intermediate = Certificate::where('type', 'intermediate')
                ->latest('id')
                ->first();

            if (!$intermediate) {
                throw new \Exception('No intermediate found');
            }

            $resolvedIntermediateId = basename(dirname($intermediate->crt_path));

            return [$intermediate, $resolvedIntermediateId];
        }

        // 🔥 Wenn parent_id als DB-ID kommt
        if (is_numeric($intermediateId)) {
            $intermediate = Certificate::where('type', 'intermediate')
                ->where('id', (int) $intermediateId)
                ->first();

            if (!$intermediate) {
                throw new \Exception('Intermediate not found');
            }

            $resolvedIntermediateId = basename(dirname($intermediate->crt_path));

            return [$intermediate, $resolvedIntermediateId];
        }

        // 🔥 Wenn CA-Ordnername kommt
        $intermediate = Certificate::where('type', 'intermediate')
            ->get()
            ->first(
                fn($c) => basename(dirname($c->crt_path)) === $intermediateId
            );

        if (!$intermediate) {
            throw new \Exception('Intermediate not found');
        }

        return [$intermediate, $intermediateId];
    }

    /**
     * 🔥 Baut CRL + OCSP URLs
     */
    private function buildUrls(Certificate $ca, string $intermediateId): array
    {
        if (!$ca->crl_path) {
            throw new \Exception('CRL path missing for intermediate');
        }

        $base = Setting::where('key', 'crl_base_url')->value('value');
        $ocspBase = Setting::where('key', 'ocsp_base_url')->value('value');

        if (!$base) {
            throw new \Exception('CRL base URL not configured');
        }

        if (!$ocspBase) {
            throw new \Exception('OCSP base URL not configured');
        }

        return [
            'crl' => rtrim($base, '/') . $ca->crl_path,
            'ocsp' => rtrim($ocspBase, '/') . '/ocsp',
        ];
    }

    /**
     * 🔥 CSR SIGN
     */
    public function signCsr(string $csrContent, ?string $intermediateId = null, string $type = 'tls'): array
    {
        [$intermediate, $intermediateId] = $this->resolveIntermediate($intermediateId);

        $urls = $this->buildUrls($intermediate, $intermediateId);

        $response = $this->http()
            ->timeout(10)
            ->attach('csr', $csrContent, 'request.csr')
            ->post($this->baseUrl . '/sign', [
                'intermediate' => $intermediateId,
                'type' => $type,
                'crl_url' => $urls['crl'],
                'ocsp_url' => $urls['ocsp'],
                'validity_days' => (int) (Setting::getValue('max_validity_days') ?: 365), // 🔥 NEU
            ]);

        if (!$response->successful()) {
            throw new \Exception('CA sign error: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * 🔥 ROOT IMPORT
     */
    public function importRoot(string $rootContent): string
    {
        $response = $this->http()
            ->timeout(10)
            ->attach('root', $rootContent, 'root.crt')
            ->post($this->baseUrl . '/ca/import-root');

        if (!$response->successful()) {
            throw new \Exception('CA root import error: ' . $response->body());
        }

        return $response->body();
    }

    /**
     * 🔥 INTERMEDIATE IMPORT
     */
    public function importIntermediate(
        string $root,
        string $intermediate,
        string $key,
        string $name,
        ?string $passphrase = null
    ): array {
        $payload = ['name' => $name];

        // Passphrase nur mitsenden, wenn vorhanden (verschlüsselter Key).
        if ($passphrase !== null && $passphrase !== '') {
            $payload['passphrase'] = $passphrase;
        }

        $response = $this->http()
            ->timeout(10)
            ->attach('root', $root, 'root.crt')
            ->attach('intermediate', $intermediate, 'intermediate.crt')
            ->attach('key', $key, 'intermediate.key')
            ->post($this->baseUrl . '/ca/import-intermediate', $payload);

        if (!$response->successful()) {
            throw new \Exception('CA intermediate import error: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * 🔥 OCSP CACHE CLEAR
     */
    public function clearOcspCache(): void
    {
        $this->http()
            ->timeout(2)
            ->post($this->baseUrl . '/ocsp/clear-cache');
    }

    /**
     * 🔥 SIGN FROM DATA (KEY + CSR intern)
     */
    /**
     * 🔥 SIGN FROM DATA (KEY + CSR intern)
     */
    public function signFromData(array $data): array
    {
        $type = $data['type'] ?? 'tls';

        [$intermediate, $intermediateId] = $this->resolveIntermediate(
            $data['parent_id'] ?? null
        );

        $urls = $this->buildUrls($intermediate, $intermediateId);

        $sanDns = array_values(array_filter($data['san_dns'] ?? []));
        $sanIps = array_values(array_filter($data['san_ips'] ?? []));

        // 🔥 SAN nur bei TLS erforderlich
        if ($type === 'tls' && empty($sanDns) && empty($sanIps)) {
            throw new \Exception('SAN is required for TLS certificates');
        }

        $payload = [
            'type' => $type,

            'common_name' => $data['common_name'],
            'organization' => $data['organization'] ?? null,
            'ou' => $data['ou'] ?? null,
            'locality' => $data['locality'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => $data['country'],
            'email' => $data['email'] ?? null,

            // 🔥 Nur TLS bekommt SANs
            // Client und CodeSign bekommen bewusst leere Arrays
            'san_dns' => $type === 'tls' ? $sanDns : [],
            'san_ips' => $type === 'tls' ? $sanIps : [],

            'key_type' => $data['key_type'] ?? 'rsa',

            'key_size' => ($data['key_type'] ?? 'rsa') === 'rsa'
                ? ($data['key_size'] ?? 3072)
                : null,

            'curve' => ($data['key_type'] ?? 'rsa') === 'ecdsa'
                ? ($data['curve'] ?? 'P256')
                : null,

            'intermediate' => $intermediateId,
            'crl_url' => $urls['crl'],
            'ocsp_url' => $urls['ocsp'],

            'validity_days' => (int) (Setting::getValue('max_validity_days') ?: 365),
        ];

        $response = $this->http()
            ->timeout(10)
            ->asJson()
            ->post($this->baseUrl . '/sign-from-data', $payload);

        if (!$response->successful()) {
            throw new \Exception('CA sign-from-data error: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * 🔥 ACME CSR SIGN — wird vom ACMEProxyController aufgerufen
     */
    public function signAcmeCsr(string $csrPem): array
    {
        [$intermediate, $intermediateId] = $this->resolveIntermediate(null);
        $urls = $this->buildUrls($intermediate, $intermediateId);

        $response = $this->http()
            ->timeout(10)
            ->attach('csr', $csrPem, 'request.csr')
            ->post($this->baseUrl . '/sign', [
                'intermediate'  => $intermediateId,
                'type'          => 'tls',
                'crl_url'       => $urls['crl'],
                'ocsp_url'      => $urls['ocsp'],
                'validity_days' => (int) (Setting::getValue('max_validity_days') ?: 90),
            ]);

        if (!$response->successful()) {
            throw new \Exception('CA ACME sign error: ' . $response->body());
        }

        return $response->json();
    }
}

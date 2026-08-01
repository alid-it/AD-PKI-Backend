<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $baseUrl = DB::table('settings')
            ->where('key', 'base_url')
            ->value('value');

        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            return;
        }

        $baseUrl = rtrim($baseUrl, '/');
        $httpBaseUrl = preg_replace('/^https:/i', 'http:', $baseUrl);

        $this->repairLegacyValue(
            'crl_base_url',
            array_unique([
                $baseUrl,
                $baseUrl . '/crl',
                $baseUrl . '/api/crl',
                $httpBaseUrl,
                $httpBaseUrl . '/crl',
            ]),
            $httpBaseUrl . '/api/crl'
        );

        $this->repairLegacyValue(
            'ocsp_base_url',
            array_unique([
                $baseUrl,
                $baseUrl . '/ocsp',
                $baseUrl . '/api/ocsp',
                $httpBaseUrl,
                $httpBaseUrl . '/ocsp',
            ]),
            $httpBaseUrl . '/api/ocsp'
        );

        DB::table('certificates')
            ->where('type', 'intermediate')
            ->whereNotNull('crl_path')
            ->get(['id', 'crl_path'])
            ->each(function (object $certificate): void {
                $correctPath = preg_replace('/\.pem$/i', '.crl', $certificate->crl_path);

                if ($correctPath !== $certificate->crl_path) {
                    DB::table('certificates')
                        ->where('id', $certificate->id)
                        ->update([
                            'crl_path' => $correctPath,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // This intentionally does not restore endpoints known to be invalid.
    }

    private function repairLegacyValue(string $key, array $legacyValues, string $correctValue): void
    {
        DB::table('settings')
            ->where('key', $key)
            ->whereIn('value', $legacyValues)
            ->update([
                'value' => $correctValue,
                'updated_at' => now(),
            ]);
    }
};

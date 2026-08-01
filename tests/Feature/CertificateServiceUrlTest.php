<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Setting;
use App\Services\CA\GoCAService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CertificateServiceUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_issuance_passes_public_api_urls_to_the_ca(): void
    {
        config([
            'services.ca.url' => 'http://ca.test',
            'services.ca.token' => 'test-ca-token',
        ]);

        Certificate::create([
            'type' => 'intermediate',
            'common_name' => 'Test Intermediate CA',
            'crt_path' => '/var/lib/adpki/intermediates/int-2/intermediate.crt',
            'crl_path' => '/crl/int-2.crl',
        ]);

        Setting::setValue('crl_base_url', 'http://pki.example.test/api/crl');
        Setting::setValue('ocsp_base_url', 'http://pki.example.test/api/ocsp');
        Setting::setValue('max_validity_days', '365');

        Http::fake([
            'http://ca.test/sign-from-data' => Http::response([
                'certificate' => 'test-certificate',
            ]),
        ]);

        app(GoCAService::class)->signFromData([
            'type' => 'client',
            'common_name' => 'client.example.test',
            'country' => 'DE',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://ca.test/sign-from-data'
                && $request['crl_url'] === 'http://pki.example.test/api/crl/int-2.crl'
                && $request['ocsp_url'] === 'http://pki.example.test/api/ocsp';
        });
    }
}

<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalACMESettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_numeric_intermediate_id_is_canonicalized(): void
    {
        config(['services.ca.token' => 'test-ca-token']);

        Certificate::create([
            'type' => 'root',
            'common_name' => 'Test Root CA',
            'crt_path' => '/var/lib/adpki/root/root.crt',
        ]);

        $intermediate = Certificate::create([
            'type' => 'intermediate',
            'common_name' => 'Test Intermediate CA',
            'crt_path' => '/var/lib/adpki/intermediates/int-2/intermediate.crt',
            'key_path' => '/var/lib/adpki/intermediates/int-2/private/intermediate.key',
        ]);

        $this->assertSame(2, $intermediate->id);

        Setting::setValue('active_intermediate', '2');
        Setting::setValue('crl_base_url', 'http://pki.example.test/api/crl');
        Setting::setValue('ocsp_base_url', 'http://pki.example.test/api/ocsp');
        Setting::setValue('max_validity_days', '365');
        Setting::setValue('acme_validity_days', '90');
        Setting::setValue('dns_servers', '["192.0.2.53"]');

        $response = $this
            ->withHeader('X-CA-Token', 'test-ca-token')
            ->getJson('/api/internal/acme-settings');

        $response
            ->assertOk()
            ->assertJsonPath('intermediate_id', 'int-2')
            ->assertJsonPath('validity_days', 90)
            ->assertJsonPath('crl_url', 'http://pki.example.test/api/crl/int-2.crl')
            ->assertJsonPath('ocsp_url', 'http://pki.example.test/api/ocsp');

        $this->assertSame('int-2', Setting::getValue('active_intermediate'));
    }
}

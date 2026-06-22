<?php

namespace App\Services\Notifications;

class TemplateVariableProvider
{
    public function getVariables(string $event): array
    {
        return match ($event) {

            'user_created', 'user_deleted' => [
                'user' => [
                    'username' => 'Username',
                    'firstname' => 'Vorname',
                    'lastname' => 'Nachname',
                    'email' => 'E-Mail',
                    'role' => 'Rolle',
                ],
            ],

            'ocsp_failed' => [
                'system' => [
                    'ocsp_url' => 'OCSP URL',
                ],
            ],

            'crl_failed' => [
                'system' => [
                    'intermediate' => 'Intermediate',
                    'crl_path' => 'CRL Pfad',
                ],
            ],

            'certificate_created', 'certificate_revoked' => [
                'certificate' => [
                    'common_name' => 'Common Name',
                    'serial' => 'Seriennummer',
                    'valid_from' => 'Gültig von',
                    'valid_to' => 'Gültig bis',
                    'revocation_reason' => 'Widerrufsgrund',
                ],
                'user' => [
                    'email' => 'User E-Mail',
                ],
                'links' => [
                    'download' => 'Download URL',
                ],
            ],

            'acme_certificate_issued', 'acme_certificate_revoked' => [
                'certificate' => [
                    'common_name' => 'Domain',
                    'serial' => 'Seriennummer',
                    'valid_from' => 'Gültig von',
                    'valid_to' => 'Gültig bis',
                    'acme_account_id' => 'ACME Account ID',
                ],
            ],

            'acme_account_deactivated' => [
                'account' => [
                    'account_id' => 'ACME Account ID',
                ],
            ],

            default => []
        };
    }
}

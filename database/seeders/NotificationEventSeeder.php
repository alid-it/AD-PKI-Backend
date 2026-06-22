<?php

namespace Database\Seeders;

use App\Models\NotificationEvent;
use Illuminate\Database\Seeder;

class NotificationEventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            'certificate_expiring' => [
                'title' => 'Certificate expiring soon: {{common_name}}',
                'message' => "A certificate is expiring soon.\n\nDomain: {{common_name}}\nSerial: {{serial}}\nExpires: {{valid_to}}",
            ],
            'certificate_revoked' => [
                'title' => 'Certificate revoked: {{common_name}}',
                'message' => "A certificate has been revoked.\n\nDomain: {{common_name}}\nSerial: {{serial}}\nReason: {{revocation_reason}}",
            ],
            'certificate_created' => [
                'title' => 'Certificate issued: {{common_name}}',
                'message' => "A new certificate has been issued.\n\nDomain: {{common_name}}\nSerial: {{serial}}\nValid from: {{valid_from}}\nValid to: {{valid_to}}",
            ],
            'user_created' => [
                'title' => 'New user created: {{username}}',
                'message' => "A new user has been created.\n\nUsername: {{username}}\nEmail: {{email}}\nRole: {{role}}",
            ],
            'user_deleted' => [
                'title' => 'User deleted: {{username}}',
                'message' => "A user has been deleted.\n\nUsername: {{username}}",
            ],
            'acme_failed' => [
                'title' => 'ACME challenge failed',
                'message' => "An ACME challenge has failed.\n\nDomain: {{domain}}\nReason: {{reason}}",
            ],
            'acme_certificate_issued' => [
                'title' => 'ACME certificate issued: {{common_name}}',
                'message' => "A new ACME certificate has been issued.\n\nDomain: {{common_name}}\nSerial: {{serial_number}}\nValid to: {{valid_to}}\nACME Account: {{acme_account_id}}",
            ],
            'acme_certificate_revoked' => [
                'title' => 'ACME certificate revoked: {{common_name}}',
                'message' => "An ACME certificate has been revoked.\n\nDomain: {{common_name}}\nSerial: {{serial_number}}\nReason: {{reason}}",
            ],
            'acme_account_deactivated' => [
                'title' => 'ACME account deactivated',
                'message' => "An ACME account has been deactivated.\n\nAccount ID: {{account_id}}",
            ],
            'ocsp_failed' => [
                'title' => 'OCSP check failed',
                'message' => "The OCSP responder is not reachable.\n\nURL: {{ocsp_url}}",
            ],
            'crl_failed' => [
                'title' => 'CRL update failed',
                'message' => "The CRL could not be updated.\n\nIntermediate: {{intermediate}}\nPath: {{crl_path}}",
            ],
            'system_error' => [
                'title' => 'System error',
                'message' => "A system error has occurred.\n\nDetails: {{message}}",
            ],
        ];

        foreach ($events as $eventName => $templates) {
            NotificationEvent::updateOrCreate(
                ['event' => $eventName],
                [
                    'enabled' => true,
                    'mail' => true,
                    'webhook' => true,
                    'telegram' => true,
                    'title_template' => $templates['title'],
                    'message_template' => $templates['message'],
                ]
            );
        }
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationEvent;
use App\Models\NotificationEventRecipient;

class NotificationEventRecipientSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'certificate_created' => ['superadmin'],
            'certificate_revoked' => ['superadmin'],
            'certificate_expiring' => ['superadmin'],
            'user_created' => ['superadmin'],
            'user_deleted' => ['superadmin'],
            'acme_failed' => ['superadmin'],
            'acme_certificate_issued' => ['superadmin'],
            'acme_certificate_revoked' => ['superadmin'],
            'acme_account_deactivated' => ['superadmin'],
            'ocsp_failed' => ['superadmin'],
            'crl_failed' => ['superadmin'],
            'system_error' => ['superadmin'],
        ];

        foreach ($map as $eventName => $roles) {

            $event = NotificationEvent::where('event', $eventName)->first();

            if (!$event)
                continue;

            foreach ($roles as $role) {
                NotificationEventRecipient::create([
                    'notification_event_id' => $event->id,
                    'role' => $role,
                ]);
            }
        }
    }
}
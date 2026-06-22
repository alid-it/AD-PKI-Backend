<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $events = [
            'acme_certificate_issued',
            'acme_certificate_revoked',
            'acme_account_deactivated',
        ];

        foreach ($events as $event) {
            $notifEvent = \App\Models\NotificationEvent::create([
                'event' => $event,
                'enabled' => true,
                'mail' => true,
                'webhook' => true,
                'telegram' => true,
            ]);

            \App\Models\NotificationEventRecipient::create([
                'notification_event_id' => $notifEvent->id,
                'role' => 'superadmin',
            ]);
        }
    }

    public function down(): void
    {
        \App\Models\NotificationEvent::whereIn('event', [
            'acme_certificate_issued',
            'acme_certificate_revoked',
            'acme_account_deactivated',
        ])->delete();
    }
};

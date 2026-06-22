<?php

namespace App\Services\Notifications;

use App\Models\NotificationEvent;
use App\Models\Setting;
use App\Services\Notifications\Channels\MailChannel;
use App\Services\Notifications\Channels\TelegramChannel;
use App\Services\Notifications\Channels\WebhookChannel;
use App\Models\User;
use App\Models\Role;

class NotificationEngine
{
    public function __construct(
        protected MailChannel $mailChannel,
        protected WebhookChannel $webhookChannel,
        protected TelegramChannel $telegramChannel,
        protected TemplateRenderer $renderer
    ) {}

    public function dispatch(string $eventName, array $data = []): void
    {
        $event = NotificationEvent::where('event', $eventName)->first();

        if (!$event || !$event->enabled) {
            return;
        }

        // 🔥 Templates rendern
        $title = $this->renderer->render(
            $event->title_template ?? 'Notification',
            $data
        );

        $message = $this->renderer->render(
            $event->message_template ?? '',
            $data
        );

        $payload = [
            'title' => $title,
            'message' => $message,
            ...$data
        ];

        // 🔥 Empfänger bestimmen
        $recipients = $this->resolveRecipients($event, $data);

        if ($event->mail) {
            $recipients = $this->resolveRecipients($event, $payload);

            foreach ($recipients as $email) {
                $this->mailChannel->send([
                    ...$payload,
                    'to' => $email
                ]);
            }
        }

        // 🔥 WEBHOOK
        if ($event->webhook) {
            $this->webhookChannel->send([
                'event' => $eventName,
                ...$payload,
            ]);
        }

        // 🔥 TELEGRAM
        if ($event->telegram) {
            $this->telegramChannel->send($payload);
        }
    }

    /**
     * 🔥 Zentrale Empfänger-Logik
     */
    protected function resolveRecipients(NotificationEvent $event, array $data): array
    {
        $roles = $event->recipients()->pluck('role')->toArray();

        // 🔥 Mapping
        $roleMap = [
            'superadmin' => 'SuperAdmin',
            'pki_admin' => 'PKIAdmin',
            'operator' => 'Operator',
            'auditor' => 'Auditor',
        ];

        $mappedRoles = array_map(fn($r) => $roleMap[$r] ?? $r, $roles);

        $emails = [];

        if (!empty($mappedRoles)) {
            $users = User::whereHas('role', function ($q) use ($mappedRoles) {
                $q->whereIn('name', $mappedRoles);
            })->pluck('email')->toArray();

            $emails = array_merge($emails, $users);
        }

        if (in_array('user', $roles) && !empty($data['user_email'])) {
            $emails[] = $data['user_email'];
        }

        return array_unique($emails);
    }
    // 🔥 Optional für Tests
    public function mail(): MailChannel
    {
        return $this->mailChannel;
    }

    public function webhook(): WebhookChannel
    {
        return $this->webhookChannel;
    }

    public function telegram(): TelegramChannel
    {
        return $this->telegramChannel;
    }
}

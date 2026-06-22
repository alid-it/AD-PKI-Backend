<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\NotificationEvent;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Services\Notifications\TemplateVariableProvider;
use App\Services\AuditService;

class NotificationController extends Controller
{
    public function index(TemplateVariableProvider $vars)
    {
        // Kein Audit — read-only
        $events = NotificationEvent::with('recipients')->orderBy('id')->get();

        return response()->json([
            'settings' => [
                'mail_enabled'     => Setting::getValue('mail_enabled'),
                'mail_host'        => Setting::getValue('mail_host'),
                'mail_port'        => Setting::getValue('mail_port'),
                'mail_username'    => Setting::getValue('mail_username'),
                'mail_password'    => Setting::getValue('mail_password'),
                'mail_from_email'  => Setting::getValue('mail_from_email'),
                'mail_from_name'   => Setting::getValue('mail_from_name'),
                'mail_encryption'  => Setting::getValue('mail_encryption'),

                'webhook_enabled'  => Setting::getValue('webhook_enabled'),
                'webhook_url'      => Setting::getValue('webhook_url'),
                'webhook_method'   => Setting::getValue('webhook_method'),
                'webhook_secret'   => Setting::getValue('webhook_secret'),

                'telegram_enabled'   => Setting::getValue('telegram_enabled'),
                'telegram_bot_token' => Setting::getValue('telegram_bot_token'),
                'telegram_chat_id'   => Setting::getValue('telegram_chat_id'),
            ],

            // 🔥 Events inkl. recipients
            'events' => $events,

            // 🔥 Variablen pro Event
            'variables' => collect($events)->mapWithKeys(function ($event) use ($vars) {
                return [
                    $event->event => $vars->getVariables($event->event)
                ];
            }),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'mail_enabled'     => ['required', 'boolean'],
            'mail_host'        => ['nullable', 'string'],
            'mail_port'        => ['nullable', 'string'],
            'mail_username'    => ['nullable', 'string'],
            'mail_password'    => ['nullable', 'string'],
            'mail_from_email'  => ['nullable', 'string'],
            'mail_from_name'   => ['nullable', 'string'],
            'mail_encryption'  => ['nullable', 'string'],

            'webhook_enabled'  => ['required', 'boolean'],
            'webhook_url'      => ['nullable', 'string'],
            'webhook_method'   => ['nullable', 'string'],
            'webhook_secret'   => ['nullable', 'string'],

            'telegram_enabled'   => ['required', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string'],
            'telegram_chat_id'   => ['nullable', 'string'],
        ]);

        foreach ($validated as $key => $value) {
            Setting::setValue($key, is_bool($value) ? ($value ? '1' : '0') : $value);
        }

        AuditService::log(AuditService::NOTIFICATION_SETTINGS_CHANGED, null, [
            'channels' => array_keys(array_filter([
                'mail'     => $validated['mail_enabled'],
                'webhook'  => $validated['webhook_enabled'],
                'telegram' => $validated['telegram_enabled'],
            ])),
        ]);

        return response()->json([
            'message_key' => 'backend.notifications.settings_saved',
        ]);
    }

    public function saveEvents(Request $request)
    {
        $validated = $request->validate([
            'events'                    => ['required', 'array'],
            'events.*.id'               => ['required', 'integer', 'exists:notification_events,id'],
            'events.*.enabled'          => ['required', 'boolean'],
            'events.*.mail'             => ['required', 'boolean'],
            'events.*.webhook'          => ['required', 'boolean'],
            'events.*.telegram'         => ['required', 'boolean'],
            'events.*.title_template'   => ['nullable', 'string'],
            'events.*.message_template' => ['nullable', 'string'],
        ]);

        foreach ($validated['events'] as $eventData) {
            NotificationEvent::where('id', $eventData['id'])->update([
                'enabled'          => $eventData['enabled'],
                'mail'             => $eventData['mail'],
                'webhook'          => $eventData['webhook'],
                'telegram'         => $eventData['telegram'],
                'title_template'   => $eventData['title_template'] ?? null,
                'message_template' => $eventData['message_template'] ?? null,
            ]);
        }

        AuditService::log(AuditService::NOTIFICATION_SETTINGS_CHANGED, null, [
            'updated_events' => collect($validated['events'])->pluck('id'),
        ]);

        return response()->json([
            'message_key' => 'backend.notifications.events_updated',
        ]);
    }
}

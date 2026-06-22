<?php

namespace App\Services\Notifications\Channels;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChannel
{
    public function send(array $payload): void
    {
        $botToken = Setting::getValue('telegram_bot_token');
        $chatId = Setting::getValue('telegram_chat_id');

        if (!$botToken || !$chatId) {
            Log::warning('Telegram skipped: bot token or chat id missing.');
            return;
        }

        $text = ($payload['title'] ?? 'Notification') . "\n\n" . ($payload['message'] ?? '');

        Log::info('Telegram sending...', [
            'chat_id' => $chatId,
            'text' => $text
        ]);

        try {
            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]
            );

            Log::info('Telegram response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Telegram failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
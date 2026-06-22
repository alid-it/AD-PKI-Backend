<?php

namespace App\Services\Notifications\Channels;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookChannel
{
    public function send(array $payload): void
    {
        $url = Setting::getValue('webhook_url');
        $method = strtoupper(Setting::getValue('webhook_method', 'POST'));
        $secret = Setting::getValue('webhook_secret');

        if (!$url) {
            Log::warning('Webhook skipped: no URL configured.');
            return;
        }

        Log::info('Webhook sending...', [
            'url' => $url,
            'payload' => $payload
        ]);

        try {
            $request = Http::timeout(10);

            if ($secret) {
                $request = $request->withHeaders([
                    'X-Webhook-Secret' => $secret,
                ]);
            }

            $response = match ($method) {
                'PUT' => $request->put($url, $payload),
                default => $request->post($url, $payload),
            };

            Log::info('Webhook response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Webhook failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
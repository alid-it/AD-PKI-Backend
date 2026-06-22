<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationEngine;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationTestController extends Controller
{
    public function test(string $channel, Request $request, NotificationEngine $engine)
    {
        $payload = [
            'title'   => 'Test Notification',
            'message_key' => 'backend.notifications.test_success',
            'to'      => $request->input('to')
        ];

        try {
            switch ($channel) {
                case 'mail':
                    $engine->mail()->send($payload);
                    break;
                case 'webhook':
                    $engine->webhook()->send($payload);
                    break;
                case 'telegram':
                    $engine->telegram()->send($payload);
                    break;
                default:
                    return response()->json([
                        'error_key' => 'backend.notifications.unknown_channel'
                    ], 400);
            }

            AuditService::log(AuditService::NOTIFICATION_TEST_SENT, null, [
                'channel' => $channel,
                'to'      => $request->input('to'),
            ]);

            return response()->json([
                'success' => true
            ]);
        } catch (\Throwable $e) {
            Log::error('Notification test failed', [
                'channel' => $channel,
                'error'   => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Notification failed'
            ], 500);
        }
    }
}

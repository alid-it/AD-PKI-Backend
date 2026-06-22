<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\Certificate;
use App\Services\CA\GoCAService;
use App\Services\AuditService;

class TSAController extends Controller
{
    private string $caUrl;

    public function __construct()
    {
        $this->caUrl = config('services.ca.url');
    }

    /**
     * 🔥 TSA Zertifikat generieren
     * POST /api/tsa/generate
     */
    public function generate(Request $request)
    {
        $request->validate([
            'intermediate_id' => 'required',
        ]);

        $input = $request->intermediate_id;

        // 🔥 Numerische ID → Verzeichnisname auflösen (z.B. "2" → "int-3")
        if (is_numeric($input)) {
            $cert = Certificate::where('id', $input)
                ->where('type', 'intermediate')
                ->first();

            if (!$cert || !$cert->crt_path) {
                return response()->json([
                    'error_key' => 'backend.tsa.intermediate_not_found',
                ], 404);
            }

            $intermediateId = basename(dirname($cert->crt_path)); // → "int-3"
        } else {
            $intermediateId = $input; // Bereits im "int-X" Format
        }

        try {
            $response = GoCAService::client()
                ->timeout(15)
                ->asJson()
                ->post($this->caUrl . '/tsa/generate', [
                    'intermediate_id' => $intermediateId,
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'error_key' => 'backend.tsa.generation_failed',
                    'detail' => $response->body(),
                ], 500);
            }

            // 🔥 Intermediate ID in Settings speichern
            Setting::updateOrCreate(
                ['key' => 'tsa_intermediate_id'],
                ['value' => $intermediateId]
            );

            AuditService::log('tsa.generated', null, [
                'intermediate_id' => $intermediateId,
            ]);

            return response()->json([
                'success' => true,
                'message_key' => 'backend.tsa.created',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error_key' => 'backend.tsa.ca_service_unreachable',
                'detail' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * 🔥 TSA Status abfragen
     * GET /api/tsa/status
     */
    public function status()
    {
        try {
            $response = GoCAService::client()
                ->timeout(3)
                ->get($this->caUrl . '/tsa/status');

            $data = $response->json();

            // 🔥 Gespeicherte Settings ergänzen
            $data['tsa_url'] = Setting::getValue('tsa_url');
            $data['intermediate_id'] = Setting::getValue('tsa_intermediate_id');

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json([
                'exists' => false,
                'error_key' => 'backend.tsa.ca_service_unreachable',
                'detail' => $e->getMessage(),
            ]);
        }
    }
}
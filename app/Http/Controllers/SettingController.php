<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    // 🔹 Bestehender Endpoint
    public function index()
    {
        // Kein Audit — read-only
        return response()->json([
            'crl_url' => Setting::getValue('crl_url'),
            'ocsp_url' => Setting::getValue('ocsp_url'),
        ]);
    }

    // 🔹 NEU: einzelnes Setting holen
    public function get($key)
    {
        // Kein Audit — read-only
        return response()->json([
            'value' => Setting::getValue($key)
        ]);
    }

    // 🔹 NEU: Setting speichern (Upsert)
    public function set(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'nullable|string'
        ]);

        Setting::updateOrCreate(
            ['key' => $request->key],
            ['value' => $request->value]
        );

        AuditService::log(AuditService::SETTINGS_CHANGED, null, [
            'key'   => $request->key,
            'value' => $request->value,
        ]);

        return response()->json([
            'success' => true
        ]);
    }
}

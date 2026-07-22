<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CA\GoCAService;
use App\Services\AuditService;
use App\Models\Certificate;
use Carbon\Carbon;
use App\Models\Setting;

class CAController extends Controller
{
    private const ALLOWED_PATH = '/var/lib/adpki/';

    public function importRoot(Request $request, GoCAService $ca)
    {
        $request->validate([
            'root' => 'required|file',
        ]);

        // ❗ Root darf nur einmal existieren
        if (Certificate::where('type', 'root')->exists()) {
            return response()->json([
                'error_key' => 'backend.ca.root_already_exists'
            ], 400);
        }

        $rootContent = file_get_contents($request->file('root')->getRealPath());

        // 🔥 Go Call
        try {
            $ca->importRoot($rootContent);
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Root import failed',
                'message' => $e->getMessage()
            ], 500);
        }

        // 🔥 Zertifikat parsen (echte Daten)
        $parsed = openssl_x509_parse($rootContent);

        $commonName = $parsed['subject']['CN'] ?? 'Root CA';
        $serial     = $parsed['serialNumberHex'] ?? uniqid();
        $validFrom  = isset($parsed['validFrom_time_t'])
            ? Carbon::createFromTimestamp($parsed['validFrom_time_t'])
            : now();
        $validTo    = isset($parsed['validTo_time_t'])
            ? Carbon::createFromTimestamp($parsed['validTo_time_t'])
            : now()->addYears(10);

        // 🔥 DB speichern
        $cert = Certificate::create([
            'type'          => 'root',
            'common_name'   => $commonName,
            'serial_number' => strtoupper($serial),
            'valid_from'    => $validFrom,
            'valid_to'      => $validTo,
            'crt_path'      => pki_path('root/root.crt'),
        ]);

        AuditService::caRootImported($cert);

        return response()->json([
            'message_key' => 'backend.ca.root_imported'
        ]);
    }

    public function importIntermediate(Request $request, GoCAService $ca)
    {
        $request->validate([
            'intermediate' => 'required|file',
            'key'          => 'required|file',
            'passphrase'   => 'nullable|string',
        ]);

        $passphrase = $request->input('passphrase');

        // ❗ Root aus DB holen
        $rootModel = Certificate::where('type', 'root')->first();

        if (!$rootModel) {
            return response()->json([
                'error_key' => 'backend.ca.root_not_found'
            ], 400);
        }

        // 🔥 Path Traversal Schutz
        $rootPath = realpath($rootModel->crt_path);

        if (!$rootPath || !str_starts_with($rootPath, self::ALLOWED_PATH)) {
            return response()->json([
                'error_key' => 'backend.ca.invalid_root_path'
            ], 500);
        }

        if (!file_exists($rootPath)) {
            return response()->json([
                'error_key' => 'backend.ca.root_certificate_missing'
            ], 500);
        }

        $root             = file_get_contents($rootPath);
        $intermediateCert = file_get_contents($request->file('intermediate')->getRealPath());
        $key              = file_get_contents($request->file('key')->getRealPath());

        // =====================================================
        // 🔥 1. TEMP DB ENTRY (wegen NOT NULL!)
        // =====================================================

        $cert = Certificate::create([
            'type'          => 'intermediate',
            'common_name'   => 'pending',
            'serial_number' => uniqid(),
            'valid_from'    => now(),
            'valid_to'      => now(),
            'crt_path'      => 'temp',
        ]);

        // =====================================================
        // 🔥 2. KORREKTER NAME (MIT DB ID)
        // =====================================================

        $intName = "int-{$cert->id}";

        // =====================================================
        // 🔥 3. GO CALL
        // =====================================================

        try {
            $result = $ca->importIntermediate(
                $root,
                $intermediateCert,
                $key,
                $intName,
                $passphrase
            );
        } catch (\Throwable $e) {

            // ❗ Cleanup bei Fehler
            $cert->delete();

            return response()->json([
                'error'   => 'Intermediate import failed',
                'message' => $e->getMessage()
            ], 500);
        }

        // =====================================================
        // 🔥 4. CRL PATH
        // =====================================================

        $crlPath = "/crl/{$intName}.pem";

        // =====================================================
        // 🔥 5. FINAL UPDATE (ECHTE DATEN)
        // =====================================================

        $cert->update([
            'common_name'   => $result['cn'],
            'serial_number' => strtoupper($result['serial']),
            'valid_from'    => Carbon::parse($result['valid_from']),
            'valid_to'      => Carbon::parse($result['valid_to']),
            'crt_path'      => $result['crt_path'],
            'key_path'      => $result['key_path'],
            // nullable — encrypted-Cast erledigt die Verschlüsselung.
            // Nur speichern, wenn der Key laut Go tatsächlich verschlüsselt ist.
            'key_passphrase' => ($result['key_encrypted'] ?? false) ? $passphrase : null,
            'parent_id'     => $rootModel->id,
            'crl_path'      => $crlPath,
        ]);

        // 🔥 Active Intermediate in Settings setzen
        Setting::updateOrCreate(
            ['key' => 'active_intermediate'],
            ['value' => $intName]
        );

        AuditService::caIntermediateImported($cert);

        return response()->json([
            'message_key' => 'backend.ca.intermediate_imported',
            'id'      => $intName
        ]);
    }

    public function defaultIntermediate()
    {
        $intermediates = Certificate::where('type', 'intermediate')->get();

        if ($intermediates->isEmpty()) {
            return response()->json([
                'error_key' => 'backend.ca.intermediate_not_found'
            ], 404);
        }

        // 👉 aktuell einfach ersten zurückgeben
        return response()->json([
            'id' => 'int-' . $intermediates->first()->id,
        ]);
    }

    /**
     * 🔒 INTERN — Passphrase eines verschlüsselten Intermediate-Keys.
     *
     * Nur über CATokenMiddleware (ca.token) erreichbar. Wird vom Go CA-Core
     * beim ersten Signiervorgang nach einem Neustart aufgerufen, um den
     * verschlüsselten Key im RAM zu entschlüsseln. Niemals für Frontend/extern.
     */
    public function passphrase(string $id)
    {
        $certificate = Certificate::where('type', 'intermediate')
            ->whereRaw('crt_path LIKE ?', ['%/' . $id . '/%'])
            ->firstOrFail();

        return response()->json([
            // encrypted-Cast entschlüsselt automatisch beim Lesen.
            'passphrase' => $certificate->key_passphrase,
        ]);
    }
}

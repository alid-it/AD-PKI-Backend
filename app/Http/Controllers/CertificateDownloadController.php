<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Certificate;
use App\Services\AuditService;

class CertificateDownloadController extends Controller
{
    private const ALLOWED_PATH = '/var/lib/adpki/';

    public function download(Request $request, $id)
    {
        $type = $request->query('type');

        if (!in_array($type, ['crt', 'fullchain', 'key', 'csr'])) {
            return response()->json(['error_key' => 'backend.download.invalid_type'], 400);
        }

        $cert = Certificate::findOrFail($id);

        switch ($type) {
            case 'crt':
                $path = $this->safePath($cert->crt_path);
                $filename = 'certificate.crt';
                break;

            case 'fullchain':
                if (!$cert->chain_path) {
                    return response()->json(['error_key' => 'backend.download.no_chain_available'], 404);
                }
                $path = $this->safePath($cert->chain_path);
                $filename = 'fullchain.pem';
                break;

            case 'key':
                if (!$cert->key_path) {
                    return response()->json(['error_key' => 'backend.download.no_private_key_available'], 404);
                }
                $path = $this->safePath($cert->key_path);
                $filename = 'private.key';
                break;

            case 'csr':
                // CSR Pfad ableiten (liegt im selben Ordner)
                $baseDir = dirname($this->safePath($cert->crt_path));
                $path = $baseDir . '/request.csr';
                $filename = 'request.csr';
                break;

            default:
                return response()->json([
                    'error_key' => 'backend.download.invalid_type'
                ], 400);
        }

        if (!file_exists($path)) {
            return response()->json(['error_key' => 'backend.download.file_not_found'], 404);
        }

        AuditService::certDownload($cert, $type);

        return response()->download($path, $filename);
    }

    public function downloadP12($id)
    {
        $cert = Certificate::findOrFail($id);

        if (!$cert->key_path || !$cert->crt_path) {
            return response()->json([
                'error_key' => 'backend.download.no_private_key_available'
            ], 400);
        }

        $certPath = $this->safePath($cert->crt_path);
        $keyPath = $this->safePath($cert->key_path);
        $chainPath = $cert->chain_path;

        if (!file_exists($certPath) || !file_exists($keyPath)) {
            return response()->json([
                'error_key' => 'backend.download.certificate_files_missing'
            ], 404);
        }

        // 🔥 TMP DIR
        $tmpDir = storage_path('app/tmp');

        if (!file_exists($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $p12Path = $tmpDir . "/cert-{$cert->id}.p12";

        // 🔥 BESSER: fullchain nutzen wenn vorhanden
        $inputCert = ($chainPath && file_exists($chainPath))
            ? $this->safePath($chainPath)
            : $certPath;

        $command = sprintf(
            'openssl pkcs12 -export -inkey %s -in %s -out %s -passout pass:',
            escapeshellarg($keyPath),
            escapeshellarg($inputCert),
            escapeshellarg($p12Path)
        );

        exec($command . ' 2>&1', $output, $code);

        if ($code !== 0) {
            return response()->json([
                'error_key' => 'backend.download.p12_generation_failed',
                'details' => $output
            ], 500);
        }

        AuditService::certDownload($cert, 'p12');

        return response()->download($p12Path)->deleteFileAfterSend(true);
    }

    // ---------------------------------------------------------------
    // Path Traversal Schutz
    // ---------------------------------------------------------------

    private function safePath(string $path): string
    {
        $real = realpath($path);

        if (!$real || !str_starts_with($real, self::ALLOWED_PATH)) {
            abort(403, 'Access denied');
        }

        return $real;
    }
}

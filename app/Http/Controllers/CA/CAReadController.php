<?php

namespace App\Http\Controllers\CA;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\AuditService;

class CAReadController extends Controller
{
    private const ALLOWED_PATH = '/var/lib/adpki/';

    public function root()
    {
        $root = Certificate::where('type', 'root')->firstOrFail();

        $path = $this->safePath($root->crt_path);

        AuditService::caCertDownloaded($root, 'root.crt');

        return response()->download(
            $path,
            'root.crt',
            ['Content-Type' => 'application/x-pem-file']
        );
    }

    public function intermediate($id)
    {
        $intermediate = Certificate::where('type', 'intermediate')
            ->where('id', $id)
            ->firstOrFail();

        $path = $this->safePath($intermediate->crt_path);

        AuditService::caCertDownloaded($intermediate, 'intermediate.crt');

        return response()->download(
            $path,
            'intermediate.crt',
            ['Content-Type' => 'application/x-pem-file']
        );
    }

    public function intermediateChain($id)
    {
        $intermediate = Certificate::where('type', 'intermediate')
            ->where('id', $id)
            ->firstOrFail();

        // 🔥 Chain bauen (Intermediate + Root)
        $root = Certificate::where('type', 'root')->firstOrFail();

        $intPath  = $this->safePath($intermediate->crt_path);
        $rootPath = $this->safePath($root->crt_path);

        $chain = file_get_contents($intPath)
            . file_get_contents($rootPath);

        AuditService::caChainDownloaded($intermediate);

        return response($chain, 200)
            ->header('Content-Type', 'application/x-pem-file')
            ->header('Content-Disposition', 'attachment; filename="chain.pem"');
    }

    public function latestIntermediate()
    {
        $intermediate = Certificate::where('type', 'intermediate')
            ->orderByDesc('id') // 🔥 besser als latest()
            ->firstOrFail();

        $path = $this->safePath($intermediate->crt_path);

        AuditService::caCertDownloaded($intermediate, 'intermediate.crt');

        return response()->download(
            $path,
            'intermediate.crt',
            ['Content-Type' => 'application/x-pem-file']
        );
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

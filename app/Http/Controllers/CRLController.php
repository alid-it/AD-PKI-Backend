<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class CRLController extends Controller
{
    public function show($id)
    {
        // 🔥 Go-URL
        $goUrl = config('services.ca.url') . "/crl/{$id}.pem";

        try {
            $response = \App\Services\CA\GoCAService::client()
                ->timeout(5)
                ->get($goUrl);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response('CRL service unavailable', 503);
        }

        if (!$response->successful()) {
            return response('CRL not found', 404);
        }

        return response($response->body(), 200)
            ->header('Content-Type', 'application/pkix-crl');
    }
}

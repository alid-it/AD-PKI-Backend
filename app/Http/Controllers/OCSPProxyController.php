<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OCSPProxyController extends Controller
{
    private string $caUrl;

    public function __construct()
    {
        $this->caUrl = config('services.ca.url');
    }

    /**
     * 🔥 OCSP Proxy — leitet OCSP Requests an Go CA weiter
     * Public Endpoint — kein Auth nötig (OCSP muss öffentlich erreichbar sein)
     *
     * Clients senden binäre OCSP Request Daten (DER-kodiert)
     * Laravel leitet sie an Go weiter und gibt die Response zurück
     */
    public function handle(Request $request)
    {
        try {
            $body = $request->getContent();

            $response = \App\Services\CA\GoCAService::client()
                ->timeout(5)
                ->withBody($body, 'application/ocsp-request')
                ->post($this->caUrl . '/ocsp');

            return response($response->body(), $response->status())
                ->header('Content-Type', 'application/ocsp-response')
                ->header('Cache-Control', 'no-store');

        } catch (\Exception $e) {
            return response('OCSP service unavailable', 503);
        }
    }
}
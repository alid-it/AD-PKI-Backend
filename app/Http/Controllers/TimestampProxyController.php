<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CA\GoCAService;

class TimestampProxyController extends Controller
{
    private string $caUrl;

    public function __construct()
    {
        $this->caUrl = config('services.ca.url');
    }

    /**
     * 🔥 Timestamp Proxy — leitet RFC 3161 Requests an Go CA weiter
     * Public Endpoint — muss öffentlich erreichbar sein
     *
     * Clients senden DER-kodierte TimeStampReq
     * Laravel leitet sie an Go weiter und gibt TimeStampResp zurück
     *
     * Kompatibel mit: signtool, osslsigncode, certbot, acme.sh
     */
    public function handle(Request $request)
    {
        try {
            $body = $request->getContent();

            $response = GoCAService::client()
                ->timeout(5)
                ->withBody($body, 'application/timestamp-query')
                ->post($this->caUrl . '/timestamp');

            return response($response->body(), $response->status())
                ->header('Content-Type', 'application/timestamp-reply')
                ->header('Cache-Control', 'no-store');

        } catch (\Exception $e) {
            return response('Timestamp service unavailable', 503);
        }
    }
}
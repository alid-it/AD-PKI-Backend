<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class DnsLookupController extends Controller
{
    /**
     * 🔥 DNS Lookup mit konfigurierten DNS-Servern
     * POST /api/dns/lookup { hostname: "example.com" }
     */
    public function lookup(Request $request)
    {
        $request->validate([
            'hostname' => 'required|string|max:253',
        ]);

        $hostname = trim($request->hostname);

        // 🔥 Konfigurierte DNS-Server laden
        $dnsServersRaw = Setting::getValue('dns_servers') ?? '[]';
        $dnsServers    = json_decode($dnsServersRaw, true) ?? [];
        $dnsServers    = array_filter($dnsServers); // leere Einträge entfernen

        // 🔥 Mit konfiguriertem DNS-Server auflösen
        if (!empty($dnsServers)) {
            $server = escapeshellarg($dnsServers[0]);
            $host   = escapeshellarg($hostname);

            $output     = [];
            $returnCode = 0;

            exec("dig +short +time=2 +tries=1 @{$server} {$host} 2>/dev/null", $output, $returnCode);

            $resolved = !empty($output) && $returnCode === 0;
            $ip       = $resolved ? trim($output[0]) : null;

        } else {
            // 🔥 Fallback: System DNS
            $ip       = gethostbyname($hostname);
            $resolved = $ip !== $hostname;
            $ip       = $resolved ? $ip : null;
        }

        return response()->json([
            'hostname' => $hostname,
            'resolved' => $resolved,
            'ip'       => $ip,
            'server'   => $dnsServers[0] ?? 'system',
        ]);
    }
}
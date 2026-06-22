<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use App\Models\Certificate;
use App\Models\Setting;
use App\Helpers;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $soon = $now->copy()->addDays(7);

        $total = Certificate::count();

        $expiringSoon = Certificate::where('revoked', false)
            ->whereBetween('valid_to', [$now, $soon])
            ->count();

        $revoked = Certificate::where('revoked', true)->count();

        $acmeActive = Certificate::where('is_acme', true)->exists();

        $expiring = Certificate::where('revoked', false)
            ->whereBetween('valid_to', [$now, $soon])
            ->orderBy('valid_to')
            ->limit(10)
            ->get()
            ->map(function ($cert) use ($now) {
                $days = $now->diffInDays($cert->valid_to, false);

                return [
                    'cn' => $cert->common_name,
                    'expires_in_days' => $days,
                    'status' => $days <= 2 ? 'critical' : 'warning'
                ];
            });

        $root = Certificate::where('type', 'root')->first();

        $intermediates = Certificate::where('type', 'intermediate')
            ->pluck('common_name');

        // 🔐 CRL aus DB
        $crlPath = Setting::getValue('crl_path', pki_path('crl.pem'));
        $crl = file_exists($crlPath);

        // 🌐 OCSP aus config
        $caUrl = config('services.ca.url');

        $ocsp = false;
        try {
            $ocsp = \App\Services\CA\GoCAService::client()
                ->timeout(2)
                ->get($caUrl . '/health')
                ->ok();
        } catch (\Exception $e) {
            $ocsp = false;
        }

        $system = [
            'acme' => $acmeActive,
            'crl' => $crl,
            'ocsp' => $ocsp,
        ];

        return response()->json([
            'total_certificates' => $total,
            'expiring_soon' => $expiringSoon,
            'revoked' => $revoked,
            'acme_active' => $acmeActive,
            'system' => $system,
            'expiring' => $expiring,
            'ca' => [
                'root' => $root?->common_name,
                'intermediates' => $intermediates
            ]
        ]);
    }
}
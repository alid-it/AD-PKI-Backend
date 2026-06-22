<?php

use App\Models\Setting;

if (!function_exists('pki_path')) {
    function pki_path(string $path = ''): string
    {
        $base = config('pki.base_path', '/var/lib/adpki');

        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (!function_exists('crl_path')) {
    function crl_path(): string
    {
        return Setting::getValue('crl_path', pki_path('crl.pem'));
    }
}
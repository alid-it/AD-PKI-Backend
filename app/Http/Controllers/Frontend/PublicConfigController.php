<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class PublicConfigController extends Controller
{
    public function index()
    {
        return response()->json([
            'defaultLocale' => Setting::getValue('app.default_locale', 'de'),
            'supportedLocales' => ['de', 'en', 'es', 'fr', 'it', 'tr'],
        ]);
    }
}
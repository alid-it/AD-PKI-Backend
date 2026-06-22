<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserSettingsController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'locale' => UserSetting::getValue(
                $user->id,
                'locale',
                Setting::getValue('app.default_locale', 'de')
            ),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'locale' => [
                'required',
                'string',
                Rule::in(['de', 'en', 'es', 'fr', 'it', 'tr']),
            ],
        ]);

        UserSetting::setValue(
            $request->user()->id,
            'locale',
            $validated['locale']
        );

        return response()->json([
            'message' => 'Settings updated successfully.',
            'locale' => $validated['locale'],
        ]);
    }
}
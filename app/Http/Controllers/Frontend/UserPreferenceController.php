<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreferenceController extends Controller
{
    // 🔹 GET /api/me/preferences/{key}
    public function get(string $key)
    {
        $value = UserPreference::getValue(Auth::id(), $key);

        return response()->json([
            'key'   => $key,
            'value' => $value,
        ]);
    }

    // 🔹 POST /api/me/preferences
    public function set(Request $request)
    {
        $request->validate([
            'key'   => 'required|string',
            'value' => 'nullable|string',
        ]);

        UserPreference::setValue(
            Auth::id(),
            $request->key,
            $request->value
        );

        return response()->json([
            'success' => true,
        ]);
    }
}
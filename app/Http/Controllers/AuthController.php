<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\AuditService;

class AuthController extends Controller
{
    // =========================================
    // LOGIN
    // =========================================

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $credentials = [
            'username' => $request->username,
            'password' => $request->password,
        ];

        if (!Auth::attempt($credentials)) {
            AuditService::authLoginFailed($request->username);

            return response()->json([
                'message_key' => 'backend.auth.invalid_credentials'
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $token = $user->createToken('adpki')->plainTextToken;

        AuditService::authLogin($user->username);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'          => $user->id,
                'username'    => $user->username,
                'firstname'   => $user->firstname,
                'lastname'    => $user->lastname,
                'email'       => $user->email,
                'role'        => $user->role?->name,
                'permissions' => $user->allPermissions(),
            ]
        ]);
    }

    // =========================================
    // ME
    // =========================================

    public function me(Request $request)
    {
        // Kein Audit — read-only, wird bei jedem Seitenaufruf getriggert
        $user = $request->user()->load('permissions', 'role.permissions');

        return response()->json([
            'id'          => $user->id,
            'username'    => $user->username,
            'firstname'   => $user->firstname,
            'lastname'    => $user->lastname,
            'email'       => $user->email,
            'role'        => $user->role?->name,
            'permissions' => $user->allPermissions(),
        ]);
    }

    // =========================================
    // LOGOUT
    // =========================================

    public function logout(Request $request)
    {
        AuditService::authLogout();

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message_key' => 'backend.auth.logged_out'
        ]);
    }
}

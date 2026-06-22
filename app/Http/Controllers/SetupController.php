<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SetupController extends Controller
{
    public function createAdmin(Request $request)
    {
        // Nur anlegen wenn noch kein User existiert
        if (User::count() > 0) {
            return response()->json([
                'message_key' => 'backend.setup.admin_already_exists',
                'skipped'     => true,
            ], 200);
        }

        $request->validate([
            'username'  => ['required', 'string', 'min:3', 'max:255'],
            'firstname' => ['required', 'string', 'min:3', 'max:255'],
            'lastname'  => ['required', 'string', 'min:3', 'max:255'],
            'email'     => ['required', 'email', 'max:255'],
            'password'  => ['required', 'string', 'min:8'],
        ]);

        $role = Role::where('name', 'SuperAdmin')->first();

        if (!$role) {
            return response()->json([
                'error_key' => 'backend.setup.superadmin_role_not_found',
            ], 500);
        }

        $user = User::create([
            'username'  => $request->username,
            'firstname' => $request->firstname,
            'lastname'  => $request->lastname,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role_id'   => $role->id,
        ]);

        AuditService::log(AuditService::USER_CREATED, $user, [
            'username' => $user->username,
            'email'    => $user->email,
            'role_id'  => $user->role_id,
            'source'   => 'setup',
        ]);

        return response()->json([
            'message_key' => 'backend.setup.admin_created',
            'user' => [
                'id'        => $user->id,
                'username'  => $user->username,
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'email'     => $user->email,
                'role'      => $role->name,
            ],
        ], 201);
    }
}
<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    // 🔹 Alle Permissions
    public function index()
    {
        // Kein Audit — read-only
        return response()->json(
            Permission::all()->map(function ($p) {
                return [
                    'id'    => $p->id,
                    'key'   => $p->key,
                    'label' => $p->label,
                    'group' => explode('.', $p->key)[0], // 🔥 wichtig fürs Frontend
                ];
            })
        );
    }

    // 🔹 User + Role Permissions
    public function userPermissions($id)
    {
        // Kein Audit — read-only
        $user = User::with('role.permissions', 'permissions')->findOrFail($id);

        $rolePermissions = $user->role
            ? $user->role->permissions->pluck('id')->toArray()
            : [];

        $userPermissions = $user->permissions->pluck('id')->toArray();

        return response()->json([
            'role_permissions' => $rolePermissions,
            'user_permissions' => $userPermissions,
        ]);
    }

    // 🔹 Save User Overrides
    public function saveUserPermissions(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $permissions = $request->input('permissions', []);

        // 🔥 nur overrides speichern
        $user->permissions()->sync($permissions);

        AuditService::log(AuditService::USER_PERMS_CHANGED, $user, [
            'username'    => $user->username,
            'permissions' => $permissions,
        ]);

        return response()->json([
            'success' => true
        ]);
    }

    public function rolePermissions($id)
    {
        // Kein Audit — read-only
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json(
            $role->permissions->pluck('id')->toArray()
        );
    }

    public function createRole(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
        ]);

        $role = Role::create([
            'name' => $request->name,
        ]);

        AuditService::log(
            AuditService::ROLE_CREATED,
            $role,
            [
                'role' => $role->name,
            ]
        );

        return response()->json([
            'success' => true,
            'role' => $role,
        ]);
    }

    public function deleteRole($id)
{
    $role = Role::findOrFail($id);

    // Optional: Systemrollen schützen
    if (in_array($role->name, ['admin', 'administrator', 'user'])) {
        return response()->json([
            'success' => false,
            'message_key' => 'backend.role.system_role_cannot_be_deleted'
        ], 422);
    }

    // Optional: Prüfen, ob User diese Rolle noch verwenden
    if (User::where('role_id', $role->id)->exists()) {
        return response()->json([
            'success' => false,
            'message_key' => 'backend.role.role_assigned_to_users'
        ], 422);
    }

    $roleName = $role->name;

    $role->permissions()->detach();
    $role->delete();

    AuditService::log(AuditService::ROLE_DELETED, null, [
        'role' => $roleName,
    ]);

    return response()->json([
        'success' => true
    ]);
}

    public function saveRolePermissions(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $permissions = $request->input('permissions', []);

        // 🔥 DAS IST DER KEY
        $role->permissions()->sync($permissions);

        AuditService::log(AuditService::ROLE_PERMS_CHANGED, $role, [
            'role'        => $role->name,
            'permissions' => $permissions,
        ]);

        return response()->json([
            'success' => true
        ]);
    }
}

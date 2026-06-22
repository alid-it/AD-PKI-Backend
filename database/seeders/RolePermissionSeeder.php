<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // =====================================================
        // 🔥 Rechte pro Rolle definieren
        // =====================================================

        $rolePermissions = [

            // ✅ SuperAdmin → alles + alle Zertifikate sehen
            'SuperAdmin' => [
                'certificate.create',
                'certificate.revoke',
                'certificate.view',
                'certificate.view.all',    // 🔥 alle Zertifikate
                'certificate.download',
                'certificate.request',
                'certificate.approve',
                'user.create',
                'user.delete',
                'user.update',
                'user.view',
                'acme.manage',
                'settings.manage',
            ],

            // ✅ PKIAdmin → PKI + Einstellungen + alle Zertifikate sehen
            'PKIAdmin' => [
                'certificate.create',
                'certificate.revoke',
                'certificate.view',
                'certificate.view.all',    // 🔥 alle Zertifikate
                'certificate.download',
                'certificate.request',
                'certificate.approve',
                'settings.manage',
            ],

            // ✅ Operator → beantragen + nur eigene Zertifikate sehen
            'Operator' => [
                'certificate.request',
                'certificate.view',
                'certificate.download',
            ],

            // ✅ Auditor → nur lesen + alle Zertifikate sehen
            'Auditor' => [
                'certificate.view',
                'certificate.view.all',    // 🔥 alle (read-only)
                'certificate.download',
            ],
        ];

        // =====================================================
        // 🔥 Permissions zuweisen
        // =====================================================

        foreach ($rolePermissions as $roleName => $permissionKeys) {

            $role = Role::where('name', $roleName)->first();

            if (!$role) {
                $this->command->warn("Rolle '{$roleName}' nicht gefunden — übersprungen.");
                continue;
            }

            $permissions = Permission::whereIn('key', $permissionKeys)->get();

            // 🔥 sync — idempotent, kann mehrfach ausgeführt werden
            $role->permissions()->sync($permissions->pluck('id')->toArray());

            $this->command->info("✅ {$roleName}: {$permissions->count()} Rechte gesetzt.");
        }
    }
}
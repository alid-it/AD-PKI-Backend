<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['key' => 'certificate.create',    'label' => 'Zertifikat erstellen'],
            ['key' => 'certificate.revoke',    'label' => 'Zertifikat widerrufen'],
            ['key' => 'certificate.view',      'label' => 'Zertifikate ansehen'],
            ['key' => 'certificate.download',  'label' => 'Zertifikat herunterladen'],
            ['key' => 'certificate.request',   'label' => 'Zertifikat beantragen'],
            ['key' => 'certificate.approve',   'label' => 'Zertifikat freigeben'],

            // 🔥 NEU — Drei-Stufen Zugriffskontrolle
            ['key' => 'certificate.view.team', 'label' => 'Team Zertifikate ansehen'],
            ['key' => 'certificate.view.all',  'label' => 'Alle Zertifikate ansehen'],

            ['key' => 'user.create',           'label' => 'User erstellen'],
            ['key' => 'user.delete',           'label' => 'User löschen'],
            ['key' => 'user.update',           'label' => 'User bearbeiten'],
            ['key' => 'user.view',             'label' => 'User ansehen'],

            ['key' => 'acme.manage',           'label' => 'ACME verwalten'],
            ['key' => 'settings.manage',       'label' => 'Einstellungen verwalten'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['key' => $perm['key']], $perm);
        }
    }
}
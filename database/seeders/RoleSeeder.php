<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role; // ✅ DAS FEHLT

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'SuperAdmin',
            'PKIAdmin',
            'Operator',
            'Auditor',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
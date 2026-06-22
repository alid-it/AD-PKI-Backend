<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 🔥 1. Rollen anlegen
        // 🔥 2. Permissions anlegen
        // 🔥 3. Rollen mit Permissions verknüpfen
        // 🔥 4. Notification Events anlegen
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            NotificationEventSeeder::class,
            NotificationEventRecipientSeeder::class,
            DevDatabaseSeeder::class,
        ]);

// 🔥 Admin User anlegen
        // $role = Role::where('name', 'SuperAdmin')->first();

        // User::firstOrCreate(
        //     ['username' => 'admin'],
        //     [
        //         'firstname' => 'Ali',
        //         'lastname'  => 'Admin',
        //         'email'     => 'admin@adpki.local',
        //         'password'  => Hash::make('master'),
        //         'role_id'   => $role->id,
        //     ]
        // );
    }
}
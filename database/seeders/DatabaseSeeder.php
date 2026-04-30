<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ], [
            'category' => 'admin',
        ]);

        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ], [
            'category' => 'admin',
        ]);

        // 1. Create Default Admin User
        $admin = User::updateOrCreate([
            'email' => 'admin@gmail.com',
        ], [
            'name' => 'Admin User',
            'username' => 'admin',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $admin->assignRole($adminRole);

        // 2. Create Super Admin User (full access)
        $superAdmin = User::updateOrCreate([
            'email' => 'superadmin@gmail.com',
        ], [
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
        ]);
        $superAdmin->assignRole($superAdminRole);

        // 3. Run Other Seeders
        $this->call([
            SystemUiPermissionSeeder::class,
        ]);
    }
}

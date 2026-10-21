<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * First-deploy data: roles, the platform super admin, and the first tenant with its admin.
 * Everything else (departments, semesters, requirements, members) is added in the app.
 * See CREDENTIALS.md and the in-app Setup Guide.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin', 'admin', 'dean', 'program_chair', 'faculty'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Platform super admin (no tenant)
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@profsync.com'],
            [
                'name' => 'ProfSync Super Admin',
                'password' => Hash::make('password'),
                'status' => 'approved',
            ]
        );
        $superAdmin->syncRoles(['super_admin']);

        // First tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'prmsu-castillejos'],
            ['name' => 'PRMSU Castillejos', 'status' => 'active']
        );

        $tenantAdmin = User::firstOrCreate(
            ['email' => 'castillejos@prmsu.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'PRMSU Castillejos Admin',
                'password' => Hash::make('password'),
                'status' => 'approved',
            ]
        );
        $tenantAdmin->syncRoles(['admin']);
    }
}

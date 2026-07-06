<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view',
            'servers.view', 'servers.manage',
            'services.view', 'services.manage',
            'websites.view', 'websites.manage',
            'databases.view', 'databases.manage',
            'docker.view', 'docker.manage',
            'backups.view', 'backups.manage',
            'users.view', 'users.manage',
            'modules.view', 'modules.manage',
            'plugins.view', 'plugins.manage',
            'updates.view', 'updates.manage',
            'license.view', 'license.manage',
            'ai.view', 'ai.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $superAdmin = Role::findOrCreate('super-admin');
        $superAdmin->givePermissionTo(Permission::all());

        Role::findOrCreate('admin');
        Role::findOrCreate('technician');
        Role::findOrCreate('client');
    }
}

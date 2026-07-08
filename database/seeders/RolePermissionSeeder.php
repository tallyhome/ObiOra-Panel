<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\PanelPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PanelPermissions::ALL as $permission) {
            Permission::findOrCreate($permission);
        }

        foreach (PanelPermissions::roleNames() as $roleName) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions(PanelPermissions::forRole($roleName));
        }
    }
}

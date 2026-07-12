<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class PostDeployCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_deploy_syncs_permissions_without_migrate_flag(): void
    {
        $this->seed(RolePermissionSeeder::class);

        Permission::query()->where('name', 'docker.manage')->delete();
        $this->assertFalse(Permission::query()->where('name', 'docker.manage')->exists());

        $this->artisan('obiora:post-deploy', ['--skip-migrate' => true])
            ->assertSuccessful();

        $this->assertTrue(Permission::query()->where('name', 'docker.manage')->exists());
        $this->assertTrue(Permission::query()->where('name', 'monitoring.manage')->exists());
    }

    public function test_post_deploy_seeds_default_alert_policies(): void
    {
        $this->artisan('obiora:post-deploy', ['--skip-migrate' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('alert_contacts', ['name' => 'Default Contact']);
        $this->assertDatabaseHas('alert_policies', ['name' => 'High Disk Usage']);
        $this->assertDatabaseHas('alert_policies', ['name' => 'Monitor Down']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\PanelPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_roles_receive_expected_permissions(): void
    {
        $superAdmin = Role::findByName('super-admin');
        $admin = Role::findByName('admin');
        $technician = Role::findByName('technician');
        $client = Role::findByName('client');

        $this->assertCount(count(PanelPermissions::ALL), $superAdmin->permissions);
        $this->assertTrue($admin->hasPermissionTo('servers.manage'));
        $this->assertFalse($admin->hasPermissionTo('users.manage'));
        $this->assertFalse($admin->hasPermissionTo('updates.manage'));
        $this->assertTrue($technician->hasPermissionTo('monitoring.view'));
        $this->assertFalse($technician->hasPermissionTo('servers.manage'));
        $this->assertTrue($client->hasPermissionTo('plugins.manage'));
        $this->assertTrue($client->hasPermissionTo('docker.view'));
        $this->assertTrue($client->hasPermissionTo('docker.manage'));
        $this->assertTrue($client->hasPermissionTo('updates.view'));
        $this->assertTrue($client->hasPermissionTo('license.view'));
        $this->assertFalse($client->hasPermissionTo('servers.view'));
        $this->assertFalse($client->hasPermissionTo('monitoring.view'));
    }

    public function test_client_cannot_access_servers_but_can_open_marketplace(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)->get(route('servers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('plugins.index'))->assertOk();
        $this->actingAs($user)->get(route('docker.index'))->assertOk();
        $this->actingAs($user)->get(route('profile.index'))->assertOk();
        $this->actingAs($user)->get(route('settings.index'))->assertOk();
    }

    public function test_demo_client_can_view_settings_but_not_manage_updates(): void
    {
        $user = User::factory()->create([
            'is_demo' => true,
            'demo_expires_at' => now()->addDay(),
        ]);
        $user->assignRole('client');

        $this->actingAs($user)->get(route('settings.index'))->assertOk();
        $this->assertFalse($user->can('updates.manage'));
        $this->assertFalse($user->can('license.manage'));
    }

    public function test_technician_can_monitor_but_not_manage_servers(): void
    {
        $user = User::factory()->create();
        $user->assignRole('technician');

        $this->actingAs($user)->get(route('monitoring.index'))->assertOk();
        $this->actingAs($user)->get(route('servers.create'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
    }

    public function test_admin_can_manage_servers_but_not_users_or_updates(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get(route('servers.index'))->assertOk();
        $this->actingAs($user)->get(route('servers.create'))->assertOk();
        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.index'))->assertOk();
    }
}

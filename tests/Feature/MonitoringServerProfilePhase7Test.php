<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MonitoringIncident;
use App\Models\Server;
use App\Models\User;
use App\Services\Monitoring\ServerUnifiedProfileService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MonitoringServerProfilePhase7Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_server_unified_profile_page_renders(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        $server = Server::factory()->create(['name' => 'Node Alpha']);

        $this->actingAs($user)
            ->get(route('monitoring.servers.show', $server))
            ->assertOk()
            ->assertSee('Node Alpha')
            ->assertSee('Vue d\'ensemble')
            ->assertSee('Monitor+');
    }

    public function test_servers_list_links_to_unified_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        $server = Server::factory()->create(['name' => 'Node Beta']);

        $this->actingAs($user)
            ->get(route('monitoring.servers'))
            ->assertOk()
            ->assertSee(route('monitoring.servers.show', $server))
            ->assertSee('Fiche');
    }

    public function test_incidents_include_server_profile_action_links(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        $server = Server::factory()->create(['name' => 'Node Gamma']);

        MonitoringIncident::query()->create([
            'resource_type' => 'server',
            'resource_id' => $server->id,
            'resource_name' => $server->name,
            'trigger' => 'High disk usage',
            'message' => 'Partition / at 98%',
            'went_down_at' => now(),
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->get(route('monitoring.incidents'))
            ->assertOk()
            ->assertSee(route('monitoring.servers.show', $server))
            ->assertSee('Doctor — disque');
    }

    public function test_unified_profile_service_aggregates_open_incidents(): void
    {
        $server = Server::factory()->create();

        MonitoringIncident::query()->create([
            'resource_type' => 'server',
            'resource_id' => $server->id,
            'resource_name' => $server->name,
            'trigger' => 'Server offline',
            'message' => 'Ping timeout',
            'went_down_at' => now(),
            'status' => 'open',
        ]);

        $profile = app(ServerUnifiedProfileService::class)->profile($server);

        $this->assertSame(1, $profile['monitoring']['open_incidents']);
        $this->assertCount(1, $profile['monitoring']['open_incident_rows']);
        $this->assertNotEmpty($profile['links']);
    }

    public function test_server_show_tabs_switch_via_livewire(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        $server = Server::factory()->create();

        Livewire::actingAs($user)
            ->test(\Modules\Monitoring\Livewire\MonitoringServerShow::class, ['server' => $server])
            ->assertSet('activeTab', 'overview')
            ->call('setTab', 'actions')
            ->assertSet('activeTab', 'actions')
            ->assertSee('Crash Analyzer');
    }
}

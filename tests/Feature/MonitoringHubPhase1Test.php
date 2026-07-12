<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MonitoringHubPhase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_monitoring_dashboard_renders_summary(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        Server::factory()->master()->create();

        $this->actingAs($user)
            ->get(route('monitoring.index'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Serveurs')
            ->assertSee('Incidents ouverts');
    }

    public function test_monitoring_servers_page_lists_servers(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        Server::factory()->create(['name' => 'Datacenter Test']);

        $this->actingAs($user)
            ->get(route('monitoring.servers'))
            ->assertOk()
            ->assertSee('Datacenter Test');
    }

    public function test_preferences_save_timezone(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $user->assignRole(Role::findByName('super-admin'));

        Livewire::actingAs($user)
            ->test(\Modules\Monitoring\Livewire\MonitoringPreferencesIndex::class)
            ->set('timezone', 'Europe/Paris')
            ->call('save')
            ->assertSet('timezone', 'Europe/Paris');

        $this->assertSame('Europe/Paris', $user->fresh()->timezone);
    }
}

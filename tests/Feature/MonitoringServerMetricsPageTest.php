<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MonitoringServerMetricsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_server_metrics_page_renders_without_samples(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        $server = Server::factory()->create(['name' => 'Metrics Node']);

        $this->actingAs($user)
            ->get(route('monitoring.servers.metrics', $server))
            ->assertOk()
            ->assertSee('Metrics Node')
            ->assertSee('Overview');
    }

    public function test_server_metrics_livewire_renders_chart_payload(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        $server = Server::factory()->create();

        Livewire::actingAs($user)
            ->test(\Modules\Monitoring\Livewire\MonitoringServerMetricsIndex::class, ['server' => $server])
            ->assertOk()
            ->call('setPreset', '1h')
            ->assertSet('timePreset', '1h');
    }
}

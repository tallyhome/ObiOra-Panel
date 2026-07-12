<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerMetricSample;
use App\Models\User;
use App\Support\MonitorInstallHelper;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MonitorAgentRemoteDeployTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_install_command_includes_server_id(): void
    {
        $server = Server::factory()->create(['agent_token' => str_repeat('a', 64)]);
        $cmd = app(MonitorInstallHelper::class)->installCommand($server);

        $this->assertStringContainsString('--server-id='.$server->id, $cmd);
        $this->assertStringContainsString('monitor-agent.sh', $cmd);
    }

    public function test_server_metrics_api_returns_series(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        $server = Server::factory()->create();

        ServerMetricSample::query()->create([
            'server_id' => $server->id,
            'sampled_at' => now()->subMinutes(5),
            'cpu_percent' => 25.5,
            'memory_percent' => 60.0,
            'disk_percent' => 40.0,
            'load_1' => 0.5,
            'load_5' => 0.4,
            'load_15' => 0.3,
        ]);

        $this->actingAs($user)
            ->getJson(route('monitoring.api.server-metrics', ['server' => $server->id, 'preset' => '24h']))
            ->assertOk()
            ->assertJsonPath('server.id', $server->id)
            ->assertJsonStructure(['series' => ['cpu', 'memory', 'disk', 'load']]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CrashHunterMetric;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CrashHunterApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_crash_hunter_metrics_ingest_with_agent_token(): void
    {
        $server = Server::factory()->create(['agent_token' => str_repeat('h', 64)]);
        $user = User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::findByName('super-admin'));
        $this->actingAs($user);

        $response = $this->postJson("/api/v1/servers/{$server->id}/crash-hunter/metrics", [
            'hostname' => 'dedie-test',
            'timestamp_us' => now()->toIso8601String(),
            'metrics' => [
                'cpu' => ['total_percent' => 42.5],
                'system' => ['load_1' => 2.1],
            ],
        ], [
            'Authorization' => 'Bearer '.str_repeat('h', 64),
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('crash_hunter_metrics', [
            'server_id' => $server->id,
            'collector' => 'cpu',
        ]);
    }

    public function test_crash_hunter_witness_ingest(): void
    {
        $server = Server::factory()->create(['agent_token' => str_repeat('w', 64)]);

        $response = $this->postJson("/api/v1/servers/{$server->id}/crash-hunter/witness", [
            'host' => 'dedie-01',
            'cpu_percent' => 55,
            'uptime_seconds' => 3600,
        ], [
            'Authorization' => 'Bearer '.str_repeat('w', 64),
        ]);

        $response->assertOk()->assertJsonPath('status', 'alive');
        $this->assertDatabaseHas('crash_hunter_witness', ['server_id' => $server->id]);
    }

    public function test_crash_hunter_dashboard_requires_auth(): void
    {
        $server = Server::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::findByName('super-admin'));
        $this->actingAs($user);

        CrashHunterMetric::query()->create([
            'server_id' => $server->id,
            'collector' => 'cpu',
            'sampled_at' => now(),
            'payload' => ['total_percent' => 10],
        ]);

        $response = $this->getJson(route('crash-hunter.api.dashboard', $server));
        $response->assertOk()->assertJsonStructure(['summary', 'charts', 'events']);
    }

    public function test_install_crash_hunter_script_is_public(): void
    {
        $response = $this->get(route('install.crash-hunter'));
        $response->assertOk();
        $this->assertStringContainsString('CrashHunter', $response->getContent());
    }
}

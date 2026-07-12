<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\ServerMetricSample;
use App\Services\Monitoring\ServerMetricsHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ServerMonitorMetricsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_push_monitor_metrics(): void
    {
        $server = Server::factory()->create(['agent_token' => str_repeat('f', 64)]);

        $payload = [
            'schema_version' => 1,
            'agent_version' => '1.0.0',
            'sampled_at' => now()->timestamp,
            'cpu_percent' => 12.5,
            'cpu_steal_percent' => 1.2,
            'memory_percent' => 55.0,
            'disk_percent' => 40.0,
            'load_1' => 0.42,
            'load_5' => 0.38,
            'load_15' => 0.35,
            'uptime_seconds' => 86400,
            'payload' => ['network' => []],
        ];

        $response = $this->postJson("/api/v1/servers/{$server->id}/monitor/metrics", $payload, [
            'Authorization' => 'Bearer '.$server->agent_token,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('next_push_seconds', 60);

        $this->assertDatabaseHas('server_metric_samples', [
            'server_id' => $server->id,
        ]);

        $server->refresh();
        $this->assertSame(ServerStatus::Online, $server->status);
        $this->assertNotNull($server->last_seen_at);
    }

    public function test_daily_info_updates_server_os_fields(): void
    {
        $server = Server::factory()->create([
            'agent_token' => str_repeat('g', 64),
            'ip_address' => '127.0.0.1',
        ]);

        $this->postJson("/api/v1/servers/{$server->id}/monitor/metrics", [
            'sampled_at' => now()->timestamp,
            'cpu_percent' => 1.0,
            'daily_info' => [
                'os_name' => 'AlmaLinux',
                'os_version' => '10',
                'primary_ip' => '203.0.113.10',
                'kernel' => '6.1.0',
            ],
        ], [
            'Authorization' => 'Bearer '.$server->agent_token,
        ])->assertOk();

        $server->refresh();
        $this->assertSame('AlmaLinux', $server->os_name);
        $this->assertSame('10', $server->os_version);
        $this->assertSame('203.0.113.10', $server->ip_address);
    }

    public function test_stale_metrics_mark_server_offline(): void
    {
        $server = Server::factory()->create([
            'status' => ServerStatus::Online,
            'metadata' => [
                'monitor_metrics' => [
                    'last_at' => now()->subMinutes(20)->toIso8601String(),
                ],
            ],
        ]);

        ServerMetricSample::query()->create([
            'server_id' => $server->id,
            'sampled_at' => now()->subMinutes(20),
            'cpu_percent' => 1.0,
        ]);

        app(ServerMetricsHealthService::class)->evaluateAll();

        $server->refresh();
        $this->assertSame(ServerStatus::Offline, $server->status);
    }

    public function test_degraded_window_between_three_and_fifteen_minutes(): void
    {
        $server = Server::factory()->create([
            'status' => ServerStatus::Online,
            'metadata' => [
                'monitor_metrics' => [
                    'last_at' => now()->subMinutes(8)->toIso8601String(),
                ],
            ],
        ]);

        app(ServerMetricsHealthService::class)->evaluateAll();

        $server->refresh();
        $this->assertSame(ServerStatus::Degraded, $server->status);
    }
}

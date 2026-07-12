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

    public function test_crash_hunter_metrics_updates_version_from_payload(): void
    {
        $server = Server::factory()->create([
            'agent_token' => str_repeat('v', 64),
            'metadata' => [
                'crash_hunter' => ['version' => '2.1.0'],
            ],
        ]);

        $this->postJson("/api/v1/servers/{$server->id}/crash-hunter/metrics", [
            'hostname' => 'dedie-test',
            'crashhunter_version' => '2.3.0',
            'metrics' => ['cpu' => ['total_percent' => 1.0]],
        ], [
            'Authorization' => 'Bearer '.str_repeat('v', 64),
        ])->assertOk();

        $server->refresh();
        $this->assertSame('2.3.0', $server->metadata['crash_hunter']['version'] ?? null);
    }

    public function test_crash_hunter_metrics_does_not_downgrade_version_from_stale_daemon(): void
    {
        $server = Server::factory()->create([
            'agent_token' => str_repeat('x', 64),
            'metadata' => [
                'crash_hunter' => ['version' => '2.3.0'],
            ],
        ]);

        $this->postJson("/api/v1/servers/{$server->id}/crash-hunter/metrics", [
            'hostname' => 'dedie-test',
            'crashhunter_version' => '2.1.0',
            'metrics' => ['cpu' => ['total_percent' => 1.0]],
        ], [
            'Authorization' => 'Bearer '.str_repeat('x', 64),
        ])->assertOk();

        $server->refresh();
        $this->assertSame('2.3.0', $server->metadata['crash_hunter']['version'] ?? null);
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
        $response->assertOk()->assertJsonStructure(['summary', 'charts', 'events', 'latest_report_insights']);
    }

    public function test_install_crash_hunter_script_is_public(): void
    {
        $response = $this->get(route('install.crash-hunter'));
        $response->assertOk();
        $this->assertStringContainsString('CrashHunter', $response->getContent());
    }

    public function test_crash_hunter_incident_ingest(): void
    {
        $server = Server::factory()->create(['agent_token' => str_repeat('i', 64)]);

        $response = $this->postJson("/api/v1/servers/{$server->id}/crash-hunter/incidents", [
            'incident_id' => 'Incident_20260711_120000',
            'triggers' => ['ssh_timeout', 'iowait_high'],
            'started_at' => now()->subMinutes(2)->toIso8601String(),
            'ended_at' => now()->toIso8601String(),
            'snapshot_count' => 42,
            'status' => 'ended',
        ], [
            'Authorization' => 'Bearer '.str_repeat('i', 64),
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('crash_hunter_incidents', [
            'server_id' => $server->id,
            'external_id' => 'Incident_20260711_120000',
            'snapshot_count' => 42,
        ]);
    }

    public function test_crash_hunter_events_ingest(): void
    {
        $server = Server::factory()->create(['agent_token' => str_repeat('e', 64)]);

        $response = $this->postJson("/api/v1/servers/{$server->id}/crash-hunter/events", [
            'events' => [
                [
                    'event_type' => 'incident_mode_started',
                    'severity' => 'critical',
                    'title' => 'incident_mode_started',
                    'details' => 'Emergency mode activated',
                    'detected_at' => now()->toIso8601String(),
                ],
            ],
        ], [
            'Authorization' => 'Bearer '.str_repeat('e', 64),
        ]);

        $response->assertOk()->assertJson(['events_ingested' => 1]);
        $this->assertDatabaseHas('crash_hunter_events', [
            'server_id' => $server->id,
            'event_type' => 'incident_mode_started',
        ]);
    }

    public function test_crash_hunter_witness_uses_agent_timestamp_gap(): void
    {
        $server = Server::factory()->create(['agent_token' => str_repeat('g', 64)]);
        $token = 'Bearer '.str_repeat('g', 64);

        $this->postJson("/api/v1/servers/{$server->id}/crash-hunter/witness", [
            'timestamp' => now()->subSeconds(60)->toIso8601String(),
            'host' => 'dedie-01',
        ], ['Authorization' => $token])->assertOk();

        $response = $this->postJson("/api/v1/servers/{$server->id}/crash-hunter/witness", [
            'timestamp' => now()->toIso8601String(),
            'host' => 'dedie-01',
        ], ['Authorization' => $token]);

        $response->assertOk()->assertJsonPath('status', 'dead');
    }

    public function test_crash_hunter_report_ingest_with_recommendations(): void
    {
        $server = Server::factory()->create(['agent_token' => str_repeat('r', 64)]);

        $response = $this->postJson("/api/v1/servers/{$server->id}/crash-hunter/reports", [
            'report_json' => [
                'report_id' => 'CrashReport_test',
                'generated_at' => now()->toIso8601String(),
                'reboot_detection' => ['reboot_detected' => true, 'reason' => 'hard_reset'],
                'diagnosis' => ['verdict' => 'Probable I/O stall'],
                'recommendations' => [
                    ['priority' => 'high', 'action' => 'Vérifier SMART disque', 'detail' => 'smartctl -a /dev/sda'],
                ],
            ],
        ], [
            'Authorization' => 'Bearer '.str_repeat('r', 64),
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('crash_hunter_reports', [
            'server_id' => $server->id,
            'external_id' => 'CrashReport_test',
        ]);
    }
}

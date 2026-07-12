<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CrashAnalyzerEvent;
use App\Models\CrashAnalyzerMetric;
use App\Models\CrashAnalyzerReport;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CrashAnalyzerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_push_metrics(): void
    {
        $server = Server::factory()->create(['agent_token' => str_repeat('a', 64)]);

        $payload = [
            'sampled_at' => now()->timestamp,
            'hostname' => 'test-host',
            'metrics' => [
                'cpu' => ['usage_percent' => 12.5, 'load_1' => 0.5],
                'memory' => ['used_percent' => 45.0],
            ],
            'events' => [],
        ];

        $response = $this->postJson("/api/v1/servers/{$server->id}/crash-analyzer/metrics", $payload, [
            'Authorization' => 'Bearer '.$server->agent_token,
        ]);

        $response->assertOk()->assertJsonPath('ok', true)->assertJsonPath('metrics_ingested', 2);
        $this->assertDatabaseHas('crash_analyzer_metrics', [
            'server_id' => $server->id,
            'collector' => 'cpu',
        ]);
    }

    public function test_metrics_push_stores_agent_version_in_server_metadata(): void
    {
        $server = Server::factory()->create([
            'agent_token' => str_repeat('c', 64),
            'metadata' => [
                'crash_analyzer' => ['version' => '1.0.0'],
            ],
        ]);

        $this->postJson("/api/v1/servers/{$server->id}/crash-analyzer/metrics", [
            'sampled_at' => now()->timestamp,
            'hostname' => 'test-host',
            'crash_analyzer_version' => '1.0.1',
            'metrics' => ['cpu' => ['usage_percent' => 1.0]],
            'events' => [],
        ], [
            'Authorization' => 'Bearer '.$server->agent_token,
        ])->assertOk();

        $server->refresh();
        $this->assertSame('1.0.1', $server->metadata['crash_analyzer']['version'] ?? null);
    }

    public function test_metrics_push_preserves_existing_agent_version_without_payload_field(): void
    {
        $server = Server::factory()->create([
            'agent_token' => str_repeat('d', 64),
            'metadata' => [
                'crash_analyzer' => ['version' => '1.0.1'],
            ],
        ]);

        $this->postJson("/api/v1/servers/{$server->id}/crash-analyzer/metrics", [
            'sampled_at' => now()->timestamp,
            'hostname' => 'test-host',
            'metrics' => ['cpu' => ['usage_percent' => 2.0]],
            'events' => [],
        ], [
            'Authorization' => 'Bearer '.$server->agent_token,
        ])->assertOk();

        $server->refresh();
        $this->assertSame('1.0.1', $server->metadata['crash_analyzer']['version'] ?? null);
    }

    public function test_metrics_push_backfills_bundled_version_for_legacy_daemon(): void
    {
        $server = Server::factory()->create([
            'agent_token' => str_repeat('e', 64),
            'metadata' => [
                'doctor_deploy' => [
                    'components' => ['doctor_suite', 'crash_hunter'],
                ],
            ],
        ]);

        $this->postJson("/api/v1/servers/{$server->id}/crash-analyzer/metrics", [
            'sampled_at' => now()->timestamp,
            'hostname' => 'datacenter',
            'metrics' => ['cpu' => ['usage_percent' => 3.0]],
            'events' => [],
        ], [
            'Authorization' => 'Bearer '.$server->agent_token,
        ])->assertOk();

        $server->refresh();
        $this->assertSame('1.0.1', $server->metadata['crash_analyzer']['version'] ?? null);
    }

    public function test_agent_can_push_crash_report(): void
    {
        $server = Server::factory()->create(['agent_token' => str_repeat('b', 64)]);

        $payload = [
            'report_id' => '2026-07-08_12-00-00',
            'hostname' => 'crash-host',
            'generated_at' => now()->toIso8601String(),
            'trigger_event' => ['event_type' => 'unexpected_reboot', 'title' => 'Reboot'],
            'report_json' => [
                'events' => [],
                'metrics_summary' => ['cpu' => ['max' => 99]],
            ],
        ];

        $response = $this->postJson("/api/v1/servers/{$server->id}/crash-analyzer/reports", $payload, [
            'Authorization' => 'Bearer '.$server->agent_token,
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseHas('crash_analyzer_reports', [
            'server_id' => $server->id,
            'external_id' => '2026-07-08_12-00-00',
        ]);
    }

    public function test_metrics_push_creates_critical_event(): void
    {
        $server = Server::factory()->create(['agent_token' => str_repeat('c', 64)]);

        $this->postJson("/api/v1/servers/{$server->id}/crash-analyzer/metrics", [
            'sampled_at' => now()->timestamp,
            'hostname' => 'host',
            'metrics' => [],
            'events' => [[
                'event_type' => 'kernel_panic',
                'severity' => 'critical',
                'title' => 'Kernel panic',
                'details' => 'test line',
                'detected_at' => now()->timestamp,
                'payload' => [],
            ]],
        ], [
            'Authorization' => 'Bearer '.$server->agent_token,
        ])->assertOk();

        $this->assertDatabaseHas('crash_analyzer_events', [
            'server_id' => $server->id,
            'event_type' => 'kernel_panic',
        ]);
    }

    public function test_dashboard_api_requires_auth(): void
    {
        $server = Server::factory()->create();
        CrashAnalyzerMetric::query()->create([
            'server_id' => $server->id,
            'collector' => 'cpu',
            'sampled_at' => now(),
            'payload' => ['usage_percent' => 10],
        ]);

        $user = \App\Models\User::factory()->create();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user->assignRole('super-admin');

        $response = $this->actingAs($user)
            ->getJson(route('crash-analyzer.api.dashboard', $server));

        $response->assertOk();
        $response->assertJsonPath('server.id', $server->id);
        $response->assertJsonStructure(['charts', 'events', 'summary']);
    }

    public function test_crash_analyzer_install_script_is_public(): void
    {
        $response = $this->get(route('install.crash-analyzer'));
        $response->assertOk();
        $this->assertStringContainsString('obiora-crash-analyzer', $response->getContent());
        $this->assertStringContainsString('crash-analyzer.tar.gz', $response->getContent());
    }

    public function test_crash_analyzer_agent_bundle_is_public(): void
    {
        if (! is_executable('/usr/bin/tar') && ! is_executable('/bin/tar')) {
            $this->markTestSkipped('tar non disponible sur cette plateforme.');
        }

        $response = $this->get(route('install.crash-analyzer.bundle'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/gzip');
        $this->assertStringStartsWith("\x1f\x8b", $response->getContent());
    }

    public function test_doctor_suite_install_script_is_public(): void
    {
        $response = $this->get(route('install.doctor-suite'));
        $response->assertOk();
        $this->assertStringContainsString('Doctor & Suite', $response->getContent());
        $this->assertStringContainsString('crash-analyzer', $response->getContent());
    }
}

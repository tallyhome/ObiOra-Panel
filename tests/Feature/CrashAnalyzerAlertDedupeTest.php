<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\CrashAnalyzer\CrashDetected;
use App\Models\CrashAnalyzerEvent;
use App\Models\MonitoringAlert;
use App\Models\Server;
use App\Services\Monitoring\MonitoringAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class CrashAnalyzerAlertDedupeTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_crash_event_dedupes_same_event_type_within_window(): void
    {
        $server = Server::factory()->create();
        $alerts = app(MonitoringAlertService::class);

        $first = CrashAnalyzerEvent::query()->create([
            'server_id' => $server->id,
            'event_type' => 'oom_killer',
            'severity' => 'critical',
            'title' => 'OOM Killer',
            'details' => 'Killed process 95843 (zstd)',
            'payload' => [],
            'detected_at' => now(),
            'notified' => false,
        ]);

        $second = CrashAnalyzerEvent::query()->create([
            'server_id' => $server->id,
            'event_type' => 'oom_killer',
            'severity' => 'critical',
            'title' => 'OOM Killer',
            'details' => 'Killed process 95843 (zstd) again',
            'payload' => [],
            'detected_at' => now(),
            'notified' => false,
        ]);

        $alerts->recordCrashEvent($server, $first);
        $alerts->recordCrashEvent($server, $second);

        $this->assertSame(1, MonitoringAlert::query()
            ->where('server_id', $server->id)
            ->where('type', 'crash_analyzer')
            ->whereNull('read_at')
            ->count());
    }

    public function test_resolve_stale_crash_alerts_after_quiet_period(): void
    {
        config(['crash_analyzer.alert_auto_resolve_minutes' => 60]);

        $server = Server::factory()->create();

        $alert = MonitoringAlert::query()->create([
            'server_id' => $server->id,
            'type' => 'crash_analyzer',
            'severity' => 'critical',
            'title' => 'OOM Killer',
            'message' => 'Killed process 1 (zstd)',
            'payload' => ['event_type' => 'oom_killer', 'event_id' => 1],
            'notified' => true,
        ]);
        $alert->forceFill([
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ])->save();

        $resolved = app(MonitoringAlertService::class)->resolveStaleCrashAlerts();

        $this->assertSame(1, $resolved);
        $this->assertNotNull(MonitoringAlert::query()->first()?->read_at);
    }

    public function test_ingest_event_dedupes_similar_oom_lines(): void
    {
        Event::fake([CrashDetected::class]);

        $server = Server::factory()->create(['agent_token' => str_repeat('z', 64)]);
        $service = app(\App\Services\CrashAnalyzer\CrashAnalyzerIngestService::class);

        $payload = [
            'event_type' => 'oom_killer',
            'severity' => 'critical',
            'title' => 'OOM Killer',
            'details' => '[Wed Jul 15 07:54:04 2026] Memory cgroup out of memory: Killed process 95843 (zstd)',
            'detected_at' => now()->timestamp,
            'payload' => [],
        ];

        $service->ingestEvent($server, $payload);
        $service->ingestEvent($server, array_merge($payload, [
            'details' => 'Jul 15 07:54:04 Obiora kernel: Memory cgroup out of memory: Killed process 95843 (zstd)',
        ]));

        $this->assertSame(1, CrashAnalyzerEvent::query()->where('server_id', $server->id)->count());
        Event::assertDispatchedTimes(CrashDetected::class, 1);
    }
}

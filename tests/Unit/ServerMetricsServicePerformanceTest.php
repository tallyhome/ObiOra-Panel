<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Server;
use App\Models\ServerMetricSample;
use App\Services\Monitoring\ServerMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class ServerMetricsServicePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_stays_fast_with_many_payload_heavy_samples(): void
    {
        $server = Server::factory()->create();
        $heavyPayload = [
            'network' => [
                ['iface' => 'eth0', 'rx' => 1_000_000, 'tx' => 500_000],
            ],
            'tcp_connections' => 42,
            'processes' => array_fill(0, 50, ['pid' => 1, 'name' => 'php', 'cpu' => 1.0, 'mem' => 2.0]),
            'partitions' => ['/' => ['mount' => '/', 'used_percent' => 40]],
        ];

        $base = Carbon::now()->subDays(3);
        for ($i = 0; $i < 200; $i++) {
            ServerMetricSample::query()->create([
                'server_id' => $server->id,
                'sampled_at' => $base->copy()->addMinutes($i * 20),
                'cpu_percent' => 10 + ($i % 5),
                'memory_percent' => 40,
                'disk_percent' => 50,
                'load_1' => 0.5,
                'load_5' => 0.4,
                'load_15' => 0.3,
                'payload' => $heavyPayload,
            ]);
        }

        $service = app(ServerMetricsService::class);
        $range = $service->resolvePreset('3d');

        $started = microtime(true);
        $dashboard = $service->dashboard($server, $range['from'], $range['to'], $range['resolution']);
        $elapsedMs = (microtime(true) - $started) * 1000;

        $this->assertTrue($dashboard['has_samples']);
        $this->assertLessThanOrEqual(360, count($dashboard['series']['cpu']['categories']));
        $this->assertSame('15m', $range['resolution']);
        $this->assertLessThan(2000, $elapsedMs, 'Dashboard 3d doit rester rapide même avec payloads lourds');
    }
}

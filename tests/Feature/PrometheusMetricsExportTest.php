<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerMetricSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PrometheusMetricsExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'monitoring.prometheus.enabled' => true,
            'monitoring.prometheus.token' => 'test-prom-token-12345678901234567890',
        ]);
    }

    public function test_metrics_endpoint_requires_token(): void
    {
        $this->get('/metrics')->assertUnauthorized();
    }

    public function test_metrics_endpoint_returns_prometheus_format(): void
    {
        $server = Server::factory()->create(['is_master' => false, 'name' => 'Prom Node']);

        ServerMetricSample::query()->create([
            'server_id' => $server->id,
            'sampled_at' => now(),
            'cpu_percent' => 42.5,
            'memory_percent' => 55.0,
            'disk_percent' => 70.0,
            'cpu_steal_percent' => 1.2,
        ]);

        $response = $this->get('/metrics', [
            'Authorization' => 'Bearer test-prom-token-12345678901234567890',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        $response->assertSee('obiora_server_cpu_percent');
        $response->assertSee('Prom Node');
        $response->assertSee('42.5');
    }

    public function test_metrics_disabled_returns_not_found(): void
    {
        config(['monitoring.prometheus.enabled' => false]);

        $this->get('/metrics', [
            'Authorization' => 'Bearer test-prom-token-12345678901234567890',
        ])->assertNotFound();
    }
}

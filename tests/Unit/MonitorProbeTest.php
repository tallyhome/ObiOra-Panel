<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Services\Monitoring\MonitorRunnerService;
use App\Services\Monitoring\Probes\MonitorProbeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class MonitorProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_https_probe_records_success(): void
    {
        Http::fake(['*' => Http::response('OK', 200)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Https,
            'target' => 'https://example.com',
        ]);

        $runner = new MonitorRunnerService(new MonitorProbeFactory);
        $check = $runner->runCheck($monitor->id);

        $this->assertSame('up', $check->status);
        $this->assertNotNull($check->response_ms);
        $this->assertSame(200, $check->metrics['http_code'] ?? null);
    }

    public function test_keyword_probe_detects_missing_text(): void
    {
        Http::fake(['*' => Http::response('hello world', 200)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Keyword,
            'target' => 'https://example.com',
            'keyword' => 'missing-phrase',
            'keyword_present' => true,
        ]);

        $runner = new MonitorRunnerService(new MonitorProbeFactory);
        $check = $runner->runCheck($monitor->id);

        $this->assertSame('down', $check->status);
        $this->assertFalse($check->metrics['keyword_found'] ?? true);
    }

    public function test_port_probe_checks_tcp_connect(): void
    {
        $monitor = Monitor::factory()->port(80)->create([
            'target' => '127.0.0.1',
        ]);

        $runner = new MonitorRunnerService(new MonitorProbeFactory);
        $check = $runner->runCheck($monitor->id);

        $this->assertContains($check->status, ['up', 'down']);
        $this->assertNotNull($check->response_ms);
    }
}

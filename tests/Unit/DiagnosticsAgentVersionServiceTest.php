<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Server;
use App\Services\Diagnostics\DiagnosticsAgentVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiagnosticsAgentVersionServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_detects_outdated_crash_hunter(): void
    {
        $server = Server::factory()->make([
            'metadata' => [
                'crash_hunter' => ['version' => '2.1.0'],
            ],
        ]);

        $service = app(DiagnosticsAgentVersionService::class);
        $bundled = $service->bundledVersions()['crash_hunter'];

        if ($bundled === null) {
            $this->markTestSkipped('CrashHunter version file unavailable');
        }

        $rows = $service->compare($server);
        $hunter = collect($rows)->firstWhere('component', 'crash_hunter');

        $this->assertNotNull($hunter);
        $this->assertSame($bundled, $hunter['bundled']);
        $this->assertSame('2.1.0', $hunter['remote']);
        $this->assertSame(version_compare('2.1.0', $bundled, '<'), $hunter['outdated']);
    }

    public function test_missing_remote_is_outdated(): void
    {
        $server = Server::factory()->make(['metadata' => []]);
        $service = app(DiagnosticsAgentVersionService::class);

        $this->assertContains('crash_hunter', $service->outdatedComponents($server));
    }

    public function test_stamp_deployed_versions_updates_server_metadata(): void
    {
        $server = Server::factory()->create([
            'metadata' => [
                'crash_hunter' => ['version' => '2.1.0'],
            ],
        ]);

        $service = app(DiagnosticsAgentVersionService::class);
        $bundled = $service->bundledVersions();

        if ($bundled['crash_hunter'] === null || $bundled['crash_analyzer'] === null) {
            $this->markTestSkipped('Agent version files unavailable');
        }

        $service->stampDeployedVersions($server, ['crash_hunter', 'crash_analyzer', 'doctor']);

        $server->refresh();
        $this->assertSame($bundled['crash_hunter'], $server->metadata['crash_hunter']['version'] ?? null);
        $this->assertSame($bundled['crash_analyzer'], $server->metadata['crash_analyzer']['version'] ?? null);
        $this->assertSame($bundled['doctor'], $server->metadata['doctor']['version'] ?? null);
        $this->assertFalse($service->needsUpgrade($server));
    }

    public function test_compare_includes_doctor_agent(): void
    {
        $server = Server::factory()->make([
            'metadata' => [
                'doctor' => ['version' => 'bootstrap-1.0'],
            ],
        ]);

        $service = app(DiagnosticsAgentVersionService::class);
        $doctor = collect($service->compare($server))->firstWhere('component', 'doctor');

        $this->assertNotNull($doctor);
        $this->assertSame('ObiOra Doctor', $doctor['label']);
        $this->assertSame('bootstrap-1.0', $doctor['remote']);
    }
}

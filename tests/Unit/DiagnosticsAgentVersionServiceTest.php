<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Server;
use App\Services\Diagnostics\DiagnosticsAgentVersionService;
use Tests\TestCase;

final class DiagnosticsAgentVersionServiceTest extends TestCase
{
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
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CrashHunter\CrashHunterMetricsService;
use Tests\TestCase;

final class CrashHunterMetricsServiceTest extends TestCase
{
    public function test_active_chart_definitions_skips_empty_series(): void
    {
        $service = new CrashHunterMetricsService;

        $active = $service->activeChartDefinitions([
            'cpu' => [['t' => 1, 'v' => null], ['t' => 2, 'v' => null]],
            'load' => [['t' => 1, 'v' => 1.2]],
            'iowait' => [],
            'pressure_io' => [['t' => 1, 'v' => 0]],
        ]);

        $this->assertCount(2, $active);
        $this->assertSame('ch-hunter-load', $active[0]['id']);
        $this->assertSame('ch-hunter-psi', $active[1]['id']);
    }

    public function test_active_chart_definitions_returns_empty_when_no_points(): void
    {
        $service = new CrashHunterMetricsService;

        $this->assertSame([], $service->activeChartDefinitions([
            'cpu' => [],
            'load' => [['t' => 1, 'v' => null]],
        ]));
    }
}

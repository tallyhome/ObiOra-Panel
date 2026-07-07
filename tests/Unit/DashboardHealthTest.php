<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DashboardHealth;
use PHPUnit\Framework\TestCase;

final class DashboardHealthTest extends TestCase
{
    public function test_glance_returns_status_levels(): void
    {
        $glance = DashboardHealth::glance([
            'cpu' => ['cores' => 4, 'load_1' => 3.5],
            'disk' => ['percent' => 95],
            'memory' => ['percent' => 50],
        ]);

        $this->assertSame('warning', $glance['load']);
        $this->assertSame('danger', $glance['disk']);
        $this->assertSame('ok', $glance['memory']);
    }

    public function test_format_bytes(): void
    {
        $this->assertSame('1 GiB', DashboardHealth::formatBytes(1073741824));
    }
}

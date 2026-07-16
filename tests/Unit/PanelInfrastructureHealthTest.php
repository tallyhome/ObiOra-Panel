<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PanelDatabase;
use App\Support\PanelInfrastructure;
use Tests\TestCase;

final class PanelInfrastructureHealthTest extends TestCase
{
    protected function tearDown(): void
    {
        PanelInfrastructure::resetCache();
        parent::tearDown();
    }

    public function test_ready_only_requires_database(): void
    {
        PanelInfrastructure::resetCache();

        $this->assertSame(PanelDatabase::isAvailable(true), PanelInfrastructure::isReady(true));
    }

    public function test_diagnostics_structure(): void
    {
        $diag = PanelInfrastructure::diagnostics(true);

        $this->assertArrayHasKey('ready', $diag);
        $this->assertArrayHasKey('database', $diag);
        $this->assertArrayHasKey('redis_required', $diag);
        $this->assertArrayHasKey('disk_ok', $diag);
        $this->assertArrayHasKey('hints', $diag);
    }

    public function test_disk_status_structure(): void
    {
        $disk = PanelInfrastructure::diskStatus(base_path());

        $this->assertArrayHasKey('ok', $disk);
        $this->assertArrayHasKey('free_bytes', $disk);
    }

    public function test_is_disk_space_exception(): void
    {
        $this->assertTrue(PanelInfrastructure::isDiskSpaceException(new \RuntimeException('No space left on device')));
        $this->assertFalse(PanelInfrastructure::isDiskSpaceException(new \RuntimeException('other')));
    }
}

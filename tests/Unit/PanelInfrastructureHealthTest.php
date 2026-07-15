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
        $this->assertArrayHasKey('hints', $diag);
    }
}

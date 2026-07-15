<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PanelDatabase;
use Tests\TestCase;

final class PanelDatabaseHealthTest extends TestCase
{
    protected function tearDown(): void
    {
        PanelDatabase::resetCache();
        parent::tearDown();
    }

    public function test_failure_cache_expires_so_next_check_retries(): void
    {
        PanelDatabase::resetCache();

        $reflection = new \ReflectionClass(PanelDatabase::class);
        $available = $reflection->getProperty('available');
        $available->setAccessible(true);
        $checkedAt = $reflection->getProperty('checkedAt');
        $checkedAt->setAccessible(true);

        $available->setValue(null, false);
        $checkedAt->setValue(null, time() - 10);

        $this->assertTrue(PanelDatabase::isAvailable());
    }
}

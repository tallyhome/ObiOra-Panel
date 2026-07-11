<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TimezoneCatalog;
use Tests\TestCase;

final class TimezoneCatalogTest extends TestCase
{
    public function test_paris_timezone_is_valid(): void
    {
        $this->assertTrue(TimezoneCatalog::isValid('Europe/Paris'));
        $this->assertStringContainsString('Paris', TimezoneCatalog::label('Europe/Paris'));
    }

    public function test_unknown_timezone_is_rejected(): void
    {
        $this->assertFalse(TimezoneCatalog::isValid('Invalid/Zone'));
        $this->assertFalse(TimezoneCatalog::isValid('../etc/passwd'));
    }

    public function test_choices_are_not_empty(): void
    {
        $this->assertArrayHasKey('UTC', TimezoneCatalog::choices());
        $this->assertGreaterThan(5, count(TimezoneCatalog::choices()));
    }
}

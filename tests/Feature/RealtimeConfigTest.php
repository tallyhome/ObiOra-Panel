<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Realtime;
use Tests\TestCase;

final class RealtimeConfigTest extends TestCase
{
    public function test_realtime_disabled_by_default(): void
    {
        config(['obiora.realtime.enabled' => false]);
        config(['broadcasting.default' => 'null']);

        $this->assertFalse(Realtime::enabled());
    }

    public function test_realtime_requires_reverb_connection(): void
    {
        config(['obiora.realtime.enabled' => true]);
        config(['broadcasting.default' => 'null']);

        $this->assertFalse(Realtime::enabled());

        config(['broadcasting.default' => 'reverb']);

        $this->assertTrue(Realtime::enabled());
    }
}

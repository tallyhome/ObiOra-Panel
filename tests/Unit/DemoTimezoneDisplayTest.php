<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Support\PanelTimezone;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class DemoTimezoneDisplayTest extends TestCase
{
    public function test_panel_timezone_always_resolves_to_europe_paris_by_default(): void
    {
        config(['app.timezone' => 'UTC']);
        config(['obiora.default_timezone' => 'Europe/Paris']);

        $this->assertSame('Europe/Paris', PanelTimezone::resolve());
    }

    public function test_expiration_label_converts_utc_app_timezone_to_paris(): void
    {
        config(['app.timezone' => 'UTC']);
        config(['obiora.default_timezone' => 'Europe/Paris']);

        Carbon::setTestNow(Carbon::parse('2026-07-15 17:34:00', 'UTC'));

        $user = new User([
            'is_demo' => true,
            'demo_expires_at' => Carbon::parse('2026-07-15 18:34:00', 'UTC'),
        ]);

        $label = $user->demoExpiresAtLabel();

        // 18:34 UTC = 20:34 Europe/Paris (CEST)
        $this->assertStringContainsString('20:34', $label);
        $this->assertStringContainsString('Europe/Paris', $label);
        $this->assertSame('1 h', $user->demoRemainingLabel());
        $this->assertSame(60, $user->demoExpiresInMinutes());
    }

    public function test_soon_banner_only_under_30_minutes(): void
    {
        config(['app.timezone' => 'UTC']);

        Carbon::setTestNow(Carbon::parse('2026-07-15 18:10:00', 'UTC'));

        $user = new User([
            'is_demo' => true,
            'demo_expires_at' => Carbon::parse('2026-07-15 18:34:00', 'UTC'),
        ]);

        $this->assertSame(24, $user->demoExpiresInMinutes());
        $this->assertTrue($user->demoExpiresInMinutes() <= 30);
    }
}

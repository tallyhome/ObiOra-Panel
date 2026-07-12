<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use App\Services\Monitoring\MonitorRunnerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MonitoringPeriodPresetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_one_hour_preset_finds_recent_checks_with_paris_timezone_user(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $this->actingAs($user);

        $monitor = Monitor::factory()->create();
        MonitorCheck::query()->create([
            'monitor_id' => $monitor->id,
            'status' => 'up',
            'response_ms' => 120,
            'metrics' => [],
            'checked_at' => now()->subMinutes(20),
        ]);

        $runner = app(MonitorRunnerService::class);
        $range = $runner->resolvePreset('1h');
        $stats = $runner->statsForPeriod($monitor, $range['from'], $range['to']);
        $timeline = $runner->statusTimelineForPeriod($monitor, $range['from'], $range['to'], '1h');

        $this->assertSame(1, $stats['checks_total']);
        $this->assertNotEmpty(array_filter($timeline, fn (array $s) => $s['status'] === 'up'));
    }
}

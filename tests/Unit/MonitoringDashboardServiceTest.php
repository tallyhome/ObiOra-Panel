<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ServerStatus;
use App\Models\MonitoringAlert;
use App\Models\Server;
use App\Models\User;
use App\Services\Monitoring\MonitoringDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MonitoringDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_servers_and_incidents(): void
    {
        Server::factory()->master()->create(['status' => ServerStatus::Online]);
        Server::factory()->create(['status' => ServerStatus::Offline]);

        MonitoringAlert::query()->create([
            'server_id' => Server::factory()->create()->id,
            'type' => 'server_offline',
            'severity' => 'warning',
            'title' => 'Test',
            'message' => 'Offline',
        ]);

        $summary = app(MonitoringDashboardService::class)->summary();

        $this->assertSame(3, $summary['servers_total']);
        $this->assertGreaterThanOrEqual(1, $summary['servers_online']);
        $this->assertSame(1, $summary['open_incidents']);
        $this->assertSame(0, $summary['monitors_total']);
    }

    public function test_user_timezone_affects_format(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $this->actingAs($user);

        $this->assertSame('Europe/Paris', \App\Support\UserTimezone::resolve());
    }
}

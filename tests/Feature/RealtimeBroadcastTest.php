<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\DashboardMetricsUpdated;
use App\Models\Server;
use App\Models\User;
use App\Services\Realtime\RealtimeBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class RealtimeBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcaster_skips_when_realtime_disabled(): void
    {
        config(['obiora.realtime.enabled' => false, 'broadcasting.default' => 'reverb']);

        Event::fake([DashboardMetricsUpdated::class]);

        $this->app->make(RealtimeBroadcaster::class)
            ->dashboard(Server::factory()->create());

        Event::assertNotDispatched(DashboardMetricsUpdated::class);
    }

    public function test_dashboard_metrics_event_is_broadcastable(): void
    {
        $event = new DashboardMetricsUpdated(7, ['cpu' => ['usage' => 1]], []);

        $this->assertSame(7, $event->serverId);
        $this->assertSame('dashboard.metrics', $event->broadcastAs());
        $this->assertSame('private-obiora.server.7', $event->broadcastOn()->name);
    }

    public function test_monitoring_page_loads_for_admin(): void
    {
        $this->seed();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        $this->actingAs($user)
            ->get(route('monitoring.index'))
            ->assertOk()
            ->assertSee('monitoring-app', false);
    }
}

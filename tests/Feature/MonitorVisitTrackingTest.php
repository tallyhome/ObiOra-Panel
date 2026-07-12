<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\MonitorVisitDaily;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MonitorVisitTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_visit_pixel_increments_counter(): void
    {
        $monitor = Monitor::factory()->create(['track_token' => 'test-token-uuid']);

        $this->get(route('monitoring.track.pixel', ['token' => 'test-token-uuid']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        $this->assertDatabaseHas('monitor_visit_daily', [
            'monitor_id' => $monitor->id,
            'visits' => 1,
        ]);
    }

    public function test_monitor_metrics_page_shows_visit_stats(): void
    {
        $monitor = Monitor::factory()->create(['track_token' => 'abc-123']);

        MonitorVisitDaily::query()->create([
            'monitor_id' => $monitor->id,
            'visit_date' => now()->toDateString(),
            'visits' => 42,
            'unique_visitors' => 10,
        ]);

        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        $this->actingAs($user)
            ->get(route('monitoring.monitors.show', $monitor))
            ->assertOk()
            ->assertSee('Visites (30 j)')
            ->assertSee('Compteur de visites');
    }
}

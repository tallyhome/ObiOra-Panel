<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MaintenanceWindow;
use App\Models\MonitoringIncident;
use App\Models\Server;
use App\Models\User;
use App\Services\Monitoring\AlertPolicyEvaluator;
use App\Services\Monitoring\MaintenanceWindowService;
use Database\Seeders\AlertPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MonitoringMaintenancePhaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AlertPolicySeeder::class);
    }

    public function test_maintenance_page_renders_for_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        $this->actingAs($user)
            ->get(route('monitoring.maintenance'))
            ->assertOk()
            ->assertSee('Maintenance');
    }

    public function test_active_maintenance_silences_alert_evaluation(): void
    {
        $server = Server::factory()->create(['is_master' => false]);

        app(MaintenanceWindowService::class)->schedule(
            resourceType: 'server',
            resourceIds: [$server->id],
            startsAt: now()->subMinute(),
            endsAt: now()->addHour(),
            note: 'Test',
            creator: null,
        );

        $this->assertTrue(app(MaintenanceWindowService::class)->isServerSilenced($server->id));

        $policy = \App\Models\AlertPolicy::query()->where('metric', 'disk_usage_percent')->first();
        $this->assertNotNull($policy);

        $result = app(AlertPolicyEvaluator::class)->evaluateTarget($policy, 'server', $server->id);

        $this->assertSame(0, $result['opened']);
        $this->assertSame(0, $result['notified']);
    }

    public function test_scheduling_maintenance_resolves_open_incidents(): void
    {
        $server = Server::factory()->create(['is_master' => false, 'name' => 'Maintained']);

        MonitoringIncident::query()->create([
            'resource_type' => 'server',
            'resource_id' => $server->id,
            'resource_name' => $server->name,
            'trigger' => 'High disk',
            'message' => 'Test',
            'went_down_at' => now(),
            'status' => 'open',
        ]);

        app(MaintenanceWindowService::class)->schedule(
            resourceType: 'server',
            resourceIds: [$server->id],
            startsAt: now()->subMinute(),
            endsAt: now()->addHour(),
            note: null,
            creator: null,
        );

        $this->assertDatabaseHas('monitoring_incidents', [
            'resource_id' => $server->id,
            'status' => 'resolved',
        ]);
    }

    public function test_cancelled_window_is_not_active(): void
    {
        $window = MaintenanceWindow::query()->create([
            'resource_type' => 'all',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        app(MaintenanceWindowService::class)->cancel($window);

        $this->assertFalse($window->fresh()->isActive());
    }
}

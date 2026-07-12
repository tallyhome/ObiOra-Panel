<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MonitoringIncident;
use App\Models\Server;
use App\Models\User;
use App\Services\Monitoring\MonitoringAlertIntelligenceService;
use App\Services\Monitoring\MonitoringSlaService;
use App\Services\Monitoring\MonitoringWitnessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MonitoringPhase7CompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_sla_report_export_returns_html(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        $server = Server::factory()->create();

        $this->actingAs($user)
            ->get(route('monitoring.servers.sla-report', ['server' => $server, 'days' => 30]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=sla-'.$server->id.'-30d-'.now()->format('Y-m-d').'.html');
    }

    public function test_sla_service_computes_uptime(): void
    {
        $server = Server::factory()->create(['status' => 'online']);

        $report = app(MonitoringSlaService::class)->serverReport($server, 30);

        $this->assertArrayHasKey('uptime', $report);
        $this->assertArrayHasKey('30d', $report['uptime']);
    }

    public function test_alert_intelligence_merges_duplicate_incidents(): void
    {
        $server = Server::factory()->create();

        $offline = MonitoringIncident::query()->create([
            'resource_type' => 'server',
            'resource_id' => $server->id,
            'resource_name' => $server->name,
            'trigger' => 'Server offline',
            'message' => 'Ping timeout',
            'went_down_at' => now(),
            'status' => 'open',
        ]);

        MonitoringIncident::query()->create([
            'resource_type' => 'server',
            'resource_id' => $server->id,
            'resource_name' => $server->name,
            'trigger' => 'Agent no data',
            'message' => 'No metrics push',
            'went_down_at' => now(),
            'status' => 'open',
        ]);

        $result = app(MonitoringAlertIntelligenceService::class)->run();

        $this->assertSame(1, $result['merged']);
        $this->assertSame('resolved', MonitoringIncident::query()->where('trigger', 'Agent no data')->value('status'));
        $this->assertStringContainsString('fusionné', MonitoringIncident::query()->find($offline->id)->message);
    }

    public function test_witness_service_returns_fleet_summary(): void
    {
        Server::factory()->create(['name' => 'Node A']);

        $summary = app(MonitoringWitnessService::class)->fleetSummary();

        $this->assertNotEmpty($summary);
        $this->assertArrayHasKey('witness_status', $summary[0]);
    }

    public function test_hub_shows_witness_section(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        Server::factory()->create();

        $this->actingAs($user)
            ->get(route('monitoring.index'))
            ->assertOk()
            ->assertSee('CrashHunter Witness');
    }
}

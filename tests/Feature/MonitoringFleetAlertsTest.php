<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MonitoringAlert;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MonitoringFleetAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_mark_oom_alert_read_marks_all_oom_on_same_server(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        $server = Server::factory()->create();

        $first = MonitoringAlert::query()->create([
            'server_id' => $server->id,
            'type' => 'crash_analyzer',
            'severity' => 'critical',
            'title' => 'OOM Killer',
            'message' => 'Killed process 100 (crashhunter)',
            'payload' => ['event_type' => 'oom_killer', 'fingerprint' => 'oom_killer:proc=crashhunter'],
        ]);

        MonitoringAlert::query()->create([
            'server_id' => $server->id,
            'type' => 'crash_analyzer',
            'severity' => 'critical',
            'title' => 'OOM Killer',
            'message' => 'Killed process 200 (mariadbd)',
            'payload' => ['event_type' => 'oom_killer', 'fingerprint' => 'oom_killer:proc=mariadbd'],
        ]);

        $this->actingAs($user)
            ->postJson(route('monitoring.api.alerts.read', $first))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(0, MonitoringAlert::query()->whereNull('read_at')->count());
    }

    public function test_mark_all_alerts_read(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        $server = Server::factory()->create();

        MonitoringAlert::query()->create([
            'server_id' => $server->id,
            'type' => 'crash_analyzer',
            'severity' => 'warning',
            'title' => 'Test',
            'message' => 'A',
            'payload' => [],
        ]);

        MonitoringAlert::query()->create([
            'server_id' => $server->id,
            'type' => 'diagnostic_critical',
            'severity' => 'critical',
            'title' => 'Test 2',
            'message' => 'B',
            'payload' => [],
        ]);

        $this->actingAs($user)
            ->postJson(route('monitoring.api.alerts.read-all'))
            ->assertOk();

        $this->assertSame(0, MonitoringAlert::query()->whereNull('read_at')->count());
    }
}

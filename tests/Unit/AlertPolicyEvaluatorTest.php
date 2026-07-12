<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AlertPolicyOperator;
use App\Models\AlertContact;
use App\Models\AlertPolicy;
use App\Models\AlertPolicyState;
use App\Models\Monitor;
use App\Models\MonitoringIncident;
use App\Models\Server;
use App\Models\ServerMetricSample;
use App\Services\Monitoring\AlertPolicyEvaluator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AlertPolicyEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_high_disk_opens_incident_after_duration(): void
    {
        Mail::fake();

        $contact = AlertContact::query()->create([
            'name' => 'Test',
            'email' => 'alerts@example.test',
            'is_default' => true,
        ]);

        $policy = AlertPolicy::query()->create([
            'name' => 'High Disk Usage',
            'metric' => 'disk_usage_percent',
            'operator' => AlertPolicyOperator::Gt,
            'value' => 90,
            'value_unit' => '%',
            'duration_minutes' => 15,
            'repeat_minutes' => 60,
            'apply_to' => 'servers',
            'notify_contact_ids' => [$contact->id],
            'is_enabled' => true,
        ]);

        $server = Server::factory()->create(['is_master' => false]);

        ServerMetricSample::query()->create([
            'server_id' => $server->id,
            'sampled_at' => now(),
            'disk_percent' => 95.0,
        ]);

        AlertPolicyState::query()->create([
            'alert_policy_id' => $policy->id,
            'resource_type' => 'server',
            'resource_id' => $server->id,
            'condition_met_since' => now()->subMinutes(16),
        ]);

        $result = app(AlertPolicyEvaluator::class)->evaluateTarget($policy, 'server', $server->id);

        $this->assertSame(1, $result['opened']);
        $this->assertDatabaseHas('monitoring_incidents', [
            'alert_policy_id' => $policy->id,
            'resource_type' => 'server',
            'resource_id' => $server->id,
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'email',
            'status' => 'sent',
        ]);
    }

    public function test_monitor_down_opens_incident_immediately(): void
    {
        Mail::fake();

        $contact = AlertContact::query()->create([
            'name' => 'Test',
            'email' => 'alerts@example.test',
            'is_default' => true,
        ]);

        $policy = AlertPolicy::query()->create([
            'name' => 'Monitor Down',
            'metric' => 'monitor_status',
            'operator' => AlertPolicyOperator::Eq,
            'value' => 0,
            'duration_minutes' => 0,
            'repeat_minutes' => 60,
            'apply_to' => 'monitors',
            'notify_contact_ids' => [$contact->id],
            'is_enabled' => true,
        ]);

        $monitor = Monitor::factory()->create(['last_status' => 'down']);

        $result = app(AlertPolicyEvaluator::class)->evaluateTarget($policy, 'monitor', $monitor->id);

        $this->assertSame(1, $result['opened']);
        $this->assertDatabaseHas('monitoring_incidents', [
            'resource_type' => 'monitor',
            'resource_id' => $monitor->id,
            'trigger' => 'Monitor Down',
            'status' => 'open',
        ]);
    }

    public function test_recovery_resolves_open_incident(): void
    {
        $policy = AlertPolicy::query()->create([
            'name' => 'Monitor Down',
            'metric' => 'monitor_status',
            'operator' => AlertPolicyOperator::Eq,
            'value' => 0,
            'duration_minutes' => 0,
            'repeat_minutes' => 0,
            'apply_to' => 'monitors',
            'notify_contact_ids' => [],
            'is_enabled' => true,
        ]);

        $monitor = Monitor::factory()->create(['last_status' => 'up']);

        MonitoringIncident::query()->create([
            'resource_type' => 'monitor',
            'resource_id' => $monitor->id,
            'resource_name' => $monitor->name,
            'trigger' => $policy->name,
            'message' => 'Was down',
            'alert_policy_id' => $policy->id,
            'went_down_at' => now()->subHour(),
            'status' => 'open',
        ]);

        AlertPolicyState::query()->create([
            'alert_policy_id' => $policy->id,
            'resource_type' => 'monitor',
            'resource_id' => $monitor->id,
            'condition_met_since' => now()->subMinutes(5),
        ]);

        $result = app(AlertPolicyEvaluator::class)->evaluateTarget($policy, 'monitor', $monitor->id);

        $this->assertSame(1, $result['resolved']);
        $this->assertDatabaseHas('monitoring_incidents', [
            'resource_id' => $monitor->id,
            'status' => 'resolved',
        ]);
        $this->assertNotNull(MonitoringIncident::query()->first()?->recovered_at);
    }

    public function test_repeat_notification_after_interval(): void
    {
        Mail::fake();

        $contact = AlertContact::query()->create([
            'name' => 'Test',
            'email' => 'alerts@example.test',
            'is_default' => true,
        ]);

        $policy = AlertPolicy::query()->create([
            'name' => 'Monitor Down',
            'metric' => 'monitor_status',
            'operator' => AlertPolicyOperator::Eq,
            'value' => 0,
            'duration_minutes' => 0,
            'repeat_minutes' => 60,
            'apply_to' => 'monitors',
            'notify_contact_ids' => [$contact->id],
            'is_enabled' => true,
        ]);

        $monitor = Monitor::factory()->create(['last_status' => 'down']);

        $incident = MonitoringIncident::query()->create([
            'resource_type' => 'monitor',
            'resource_id' => $monitor->id,
            'resource_name' => $monitor->name,
            'trigger' => $policy->name,
            'message' => 'Down',
            'alert_policy_id' => $policy->id,
            'went_down_at' => now()->subHours(2),
            'last_notified_at' => now()->subHours(2),
            'status' => 'open',
        ]);

        AlertPolicyState::query()->create([
            'alert_policy_id' => $policy->id,
            'resource_type' => 'monitor',
            'resource_id' => $monitor->id,
            'condition_met_since' => now()->subHours(2),
        ]);

        $result = app(AlertPolicyEvaluator::class)->evaluateTarget($policy, 'monitor', $monitor->id);

        $this->assertSame(0, $result['opened']);
        $this->assertGreaterThanOrEqual(1, $result['notified']);
        $this->assertDatabaseHas('notification_logs', [
            'monitoring_incident_id' => $incident->id,
            'channel' => 'email',
            'status' => 'sent',
        ]);
    }
}

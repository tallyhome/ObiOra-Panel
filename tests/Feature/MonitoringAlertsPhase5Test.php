<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AlertPolicyOperator;
use App\Models\AlertContact;
use App\Models\AlertPolicy;
use App\Models\Monitor;
use App\Models\MonitoringIncident;
use App\Models\User;
use Database\Seeders\AlertPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MonitoringAlertsPhase5Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_alerts_page_lists_seeded_policies(): void
    {
        $this->seed(AlertPolicySeeder::class);

        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        $this->actingAs($user)
            ->get(route('monitoring.alerts'))
            ->assertOk()
            ->assertSee('High Disk Usage')
            ->assertSee('Monitor Down');
    }

    public function test_create_policy_via_livewire(): void
    {
        AlertContact::query()->create([
            'name' => 'Default',
            'email' => 'test@example.com',
            'is_default' => true,
        ]);

        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        Livewire::actingAs($user)
            ->test(\Modules\Monitoring\Livewire\MonitoringAlertsIndex::class)
            ->call('openPolicyModal')
            ->set('policyName', 'Custom CPU')
            ->set('policyMetric', 'cpu_usage_percent')
            ->set('policyOperator', 'gt')
            ->set('policyValue', '85')
            ->set('policyContactIds', [1])
            ->call('savePolicy')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('alert_policies', [
            'name' => 'Custom CPU',
            'metric' => 'cpu_usage_percent',
        ]);
    }

    public function test_evaluate_command_creates_monitor_down_incident(): void
    {
        Mail::fake();

        $contact = AlertContact::query()->create([
            'name' => 'Default',
            'email' => 'alerts@example.test',
            'is_default' => true,
        ]);

        AlertPolicy::query()->create([
            'name' => 'Monitor Down',
            'metric' => 'monitor_status',
            'operator' => AlertPolicyOperator::Eq,
            'value' => 0,
            'duration_minutes' => 0,
            'repeat_minutes' => 0,
            'apply_to' => 'monitors',
            'notify_contact_ids' => [$contact->id],
            'is_enabled' => true,
        ]);

        Monitor::factory()->create([
            'name' => 'Site KO',
            'last_status' => 'down',
            'is_active' => true,
        ]);

        $this->artisan('obiora:evaluate-alert-policies')->assertSuccessful();

        $this->assertDatabaseHas('monitoring_incidents', [
            'trigger' => 'Monitor Down',
            'status' => 'open',
        ]);
    }

    public function test_incidents_page_shows_notification_logs_tab(): void
    {
        $contact = AlertContact::query()->create([
            'name' => 'Default',
            'email' => 'test@example.com',
            'is_default' => true,
        ]);

        $incident = MonitoringIncident::query()->create([
            'resource_type' => 'monitor',
            'resource_id' => 1,
            'resource_name' => 'API',
            'trigger' => 'Monitor Down',
            'message' => 'Down',
            'went_down_at' => now(),
            'status' => 'open',
        ]);

        \App\Models\NotificationLog::query()->create([
            'monitoring_incident_id' => $incident->id,
            'alert_contact_id' => $contact->id,
            'channel' => 'email',
            'status' => 'sent',
            'response' => 'OK',
            'sent_at' => now(),
        ]);

        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        $this->actingAs($user)
            ->get(route('monitoring.incidents.logs'))
            ->assertOk()
            ->assertSee('Notification Logs')
            ->assertSee('email');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MonitoringMonitorsPhase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_monitors_page_lists_monitors(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        Monitor::factory()->create(['name' => 'API Health']);

        $this->actingAs($user)
            ->get(route('monitoring.monitors'))
            ->assertOk()
            ->assertSee('API Health');
    }

    public function test_create_monitor_via_livewire(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        Livewire::actingAs($user)
            ->test(\Modules\Monitoring\Livewire\MonitoringMonitorsIndex::class)
            ->call('openAddModal')
            ->set('name', 'Site test')
            ->set('type', 'https')
            ->set('target', 'https://example.org')
            ->set('intervalSeconds', 300)
            ->call('saveMonitor')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('monitors', [
            'name' => 'Site test',
            'target' => 'https://example.org',
        ]);
    }

    public function test_run_monitors_command_executes_due_check(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $monitor = Monitor::factory()->create([
            'target' => 'https://example.com',
            'interval_seconds' => 60,
            'last_checked_at' => null,
        ]);

        $this->artisan('obiora:run-monitors')->assertSuccessful();

        $monitor->refresh();
        $this->assertSame('up', $monitor->last_status);
        $this->assertDatabaseCount('monitor_checks', 1);
    }
}

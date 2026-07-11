<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CrashAnalyzerReport;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CrashAnalyzerReportViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_view_route_renders_inline_html(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user->assignRole('super-admin');

        $server = Server::factory()->create();
        $report = CrashAnalyzerReport::query()->create([
            'server_id' => $server->id,
            'external_id' => '2026-07-11_test',
            'hostname' => $server->hostname,
            'trigger_type' => 'rcu_stall',
            'generated_at' => now(),
            'report_json' => [
                'events' => [
                    ['event_type' => 'rcu_stall', 'severity' => 'critical', 'title' => 'RCU stall', 'details' => 'test'],
                ],
                'metrics_summary' => ['cpu_max' => 12],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('crash-analyzer.report.view', [$server, $report]));

        $response->assertOk();
        $response->assertSee('RCU stall');
        $response->assertSee('est-il passé');
    }
}

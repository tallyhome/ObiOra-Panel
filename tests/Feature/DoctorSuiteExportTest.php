<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CrashAnalyzerMetric;
use App\Models\CrashHunterMetric;
use App\Models\DiagnosticReport;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class DoctorSuiteExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_export_routes_require_auth(): void
    {
        $server = Server::factory()->create();

        foreach (['doctor.export.json', 'doctor.export.csv', 'doctor.export.html'] as $route) {
            $this->get(route($route, $server))->assertRedirect();
        }
    }

    public function test_authenticated_user_can_export_doctor_suite_json(): void
    {
        $server = Server::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        CrashAnalyzerMetric::query()->create([
            'server_id' => $server->id,
            'collector' => 'cpu',
            'sampled_at' => now(),
            'payload' => ['usage_percent' => 42],
        ]);

        CrashHunterMetric::query()->create([
            'server_id' => $server->id,
            'collector' => 'cpu',
            'sampled_at' => now(),
            'payload' => ['usage_percent' => 55],
        ]);

        DiagnosticReport::query()->create([
            'server_id' => $server->id,
            'score' => 88,
            'status' => 'healthy',
            'doctor_version' => '1.0.0',
            'generated_at' => now(),
            'report_json' => ['checks' => []],
            'critical_findings' => [],
        ]);

        $response = $this->actingAs($user)
            ->get(route('doctor.export.json', ['server' => $server, 'hours' => 24]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json; charset=utf-8');

        $payload = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($server->id, $payload['server']['id']);
        $this->assertArrayHasKey('doctor', $payload);
        $this->assertArrayHasKey('crash_analyzer', $payload);
        $this->assertArrayHasKey('crash_hunter', $payload);
        $this->assertCount(1, $payload['crash_analyzer']['metrics']);
        $this->assertCount(1, $payload['crash_hunter']['metrics']);
    }

    public function test_authenticated_user_can_export_doctor_suite_csv(): void
    {
        $server = Server::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        CrashAnalyzerMetric::query()->create([
            'server_id' => $server->id,
            'collector' => 'cpu',
            'sampled_at' => now(),
            'payload' => ['usage_percent' => 12],
        ]);

        $response = $this->actingAs($user)
            ->get(route('doctor.export.csv', ['server' => $server, 'hours' => 24]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('crash_analyzer', $csv);
        $this->assertStringContainsString('usage_percent', $csv);
    }

    public function test_authenticated_user_can_view_doctor_suite_html_inline(): void
    {
        $server = Server::factory()->create(['name' => 'Export Test Server']);
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        $response = $this->actingAs($user)
            ->get(route('doctor.export.html', ['server' => $server, 'hours' => 24, 'inline' => 1]));

        $response->assertOk();
        $response->assertSee('ObiOra Doctor &amp; Suite — export diagnostic', false);
        $response->assertSee('Export Test Server');
    }
}

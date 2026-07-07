<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiagnosticReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_push_diagnostic_report(): void
    {
        $server = Server::factory()->create([
            'agent_token' => str_repeat('a', 64),
            'metadata' => ['doctor_signing_key' => bin2hex(random_bytes(32))],
        ]);

        $payload = [
            'score' => 88,
            'version' => '1.0.0',
            'generated_at' => now()->toIso8601String(),
            'host' => ['hostname' => 'test', 'schema_version' => '1.0'],
            'results' => [],
        ];

        $response = $this->postJson("/api/v1/servers/{$server->id}/diagnostics/reports", $payload, [
            'Authorization' => 'Bearer '.$server->agent_token,
        ]);

        $response->assertOk()->assertJsonPath('ok', true)->assertJsonPath('score', 88);
        $this->assertDatabaseHas('diagnostic_reports', [
            'server_id' => $server->id,
            'score' => 88,
        ]);
    }

    public function test_invalid_agent_token_is_rejected(): void
    {
        $server = Server::factory()->create(['agent_token' => str_repeat('b', 64)]);

        $response = $this->postJson("/api/v1/servers/{$server->id}/diagnostics/reports", [
            'score' => 50,
            'results' => [],
        ], [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertUnauthorized();
    }

    public function test_agent_cannot_push_for_other_server(): void
    {
        $serverA = Server::factory()->create(['agent_token' => str_repeat('c', 64)]);
        $serverB = Server::factory()->create(['agent_token' => str_repeat('d', 64)]);

        $response = $this->postJson("/api/v1/servers/{$serverB->id}/diagnostics/reports", [
            'score' => 50,
            'results' => [],
        ], [
            'Authorization' => 'Bearer '.$serverA->agent_token,
        ]);

        $response->assertForbidden();
    }
}

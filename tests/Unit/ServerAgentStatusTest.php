<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Server;
use App\Support\ServerAgentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ServerAgentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_slave_agent_from_metadata(): void
    {
        $server = Server::factory()->create([
            'metadata' => [
                'agent_installed' => true,
                'slave_deploy' => ['deployed_at' => now()->toIso8601String()],
            ],
        ]);

        $flags = app(ServerAgentStatus::class)->flags($server);

        $this->assertTrue($flags['slave']);
        $this->assertTrue($flags['any']);
    }
}

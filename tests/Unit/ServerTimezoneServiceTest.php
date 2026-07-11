<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Server;
use App\Services\Diagnostics\ServerTimezoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ServerTimezoneServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_remote_script_command_uses_panel_install_route(): void
    {
        config(['app.url' => 'https://panel.example.test']);

        $service = app(ServerTimezoneService::class);
        $method = new \ReflectionMethod($service, 'remoteScriptCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, 'status');

        $this->assertStringContainsString('/install/server-timezone.sh', $command);
        $this->assertStringContainsString('bash -s status', $command);
    }

    public function test_status_returns_error_when_remote_unreachable(): void
    {
        $server = Server::factory()->create([
            'is_master' => false,
            'ip_address' => '203.0.113.10',
        ]);

        $status = app(ServerTimezoneService::class)->status($server, null, '203.0.113.10');

        $this->assertNull($status['timezone']);
        $this->assertNotNull($status['error']);
    }
}

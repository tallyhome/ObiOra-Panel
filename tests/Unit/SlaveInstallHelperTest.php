<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Server;
use App\Support\SlaveInstallHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SlaveInstallHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_remote_command_uses_panel_url_and_token(): void
    {
        config(['app.url' => 'http://panel.test']);
        \Illuminate\Support\Facades\URL::forceRootUrl('http://panel.test');

        $server = Server::factory()->create([
            'agent_token' => str_repeat('a', 64),
        ]);

        $command = (new SlaveInstallHelper)->remoteCommand($server);

        $this->assertStringContainsString('http://panel.test/install/slave-agent.sh', $command);
        $this->assertStringContainsString('OBIORA_AGENT_TOKEN='.str_repeat('a', 64), $command);
        $this->assertStringContainsString('OBIORA_AGENT_PORT=9100', $command);
    }
}

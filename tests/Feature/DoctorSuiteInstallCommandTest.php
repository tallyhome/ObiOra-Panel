<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Support\DoctorInstallHelper;
use Tests\TestCase;

final class DoctorSuiteInstallCommandTest extends TestCase
{
    public function test_suite_install_command_quotes_panel_url_and_token(): void
    {
        $server = Server::factory()->make([
            'id' => 4,
            'agent_token' => str_repeat('a', 64),
        ]);

        $command = app(DoctorInstallHelper::class)->suiteInstallShellCommand($server);

        $this->assertStringContainsString('/install/doctor-suite.sh', $command);
        $this->assertStringNotContainsString("'/install/doctor-suite.sh", $command);
        $this->assertStringContainsString(str_repeat('a', 64), $command);
        $this->assertSame(['doctor', 'crash_analyzer', 'crash_hunter'], app(DoctorInstallHelper::class)->suiteComponentList());
    }
}

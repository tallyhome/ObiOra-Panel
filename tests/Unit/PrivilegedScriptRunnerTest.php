<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SystemExecutorInterface;
use App\DTOs\ProcessResult;
use App\Services\System\PrivilegedScriptRunner;
use Tests\TestCase;

final class PrivilegedScriptRunnerTest extends TestCase
{
    public function test_wraps_install_env_with_sudo_env_command(): void
    {
        $executor = new class implements SystemExecutorInterface
        {
            public string $lastCommand = '';

            public function run(string $command, array $options = []): ProcessResult
            {
                $this->lastCommand = $command;

                return ProcessResult::success();
            }

            public function runScript(string $path, array $args = []): ProcessResult
            {
                return ProcessResult::success();
            }

            public function runAsUser(string $user, string $command, array $options = []): ProcessResult
            {
                return ProcessResult::success();
            }
        };

        $runner = new PrivilegedScriptRunner($executor);
        $runner->run('/opt/obiora-panel/agent/scripts/marketplace-exec.sh', ['arg'], 10, [
            'OBIORA_APP_PASS' => 'motdepasse1234',
            'OBIORA_APP_USERNAME' => 'admin',
        ]);

        $this->assertStringContainsString('env ', $executor->lastCommand);
        $this->assertStringContainsString('OBIORA_APP_PASS=', $executor->lastCommand);
        $this->assertStringContainsString('motdepasse1234', $executor->lastCommand);
        $this->assertStringNotContainsString("OBIORA_APP_PASS='motdepasse1234' sudo", $executor->lastCommand);
    }

    public function test_run_command_wraps_shell_with_bash_c(): void
    {
        $executor = new class implements SystemExecutorInterface
        {
            public string $lastCommand = '';

            public function run(string $command, array $options = []): ProcessResult
            {
                $this->lastCommand = $command;

                return ProcessResult::success();
            }

            public function runScript(string $path, array $args = []): ProcessResult
            {
                return ProcessResult::success();
            }

            public function runAsUser(string $user, string $command, array $options = []): ProcessResult
            {
                return ProcessResult::success();
            }
        };

        $runner = new PrivilegedScriptRunner($executor);
        $runner->runCommand('systemctl start obiora-doctor-agent.service', 30);

        $this->assertStringContainsString('bash -c', $executor->lastCommand);
        $this->assertStringContainsString('systemctl start obiora-doctor-agent.service', $executor->lastCommand);
        $this->assertStringNotContainsString("sudo -n 'systemctl", $executor->lastCommand);
    }

    public function test_run_script_with_env_does_not_double_wrap_bash_c(): void
    {
        $executor = new class implements SystemExecutorInterface
        {
            public string $lastCommand = '';

            public function run(string $command, array $options = []): ProcessResult
            {
                $this->lastCommand = $command;

                return ProcessResult::success();
            }

            public function runScript(string $path, array $args = []): ProcessResult
            {
                return ProcessResult::success();
            }

            public function runAsUser(string $user, string $command, array $options = []): ProcessResult
            {
                return ProcessResult::success();
            }
        };

        $runner = new PrivilegedScriptRunner($executor);
        $runner->run('/opt/obiora-panel/agent/scripts/run-security-scan.sh', [], 300, [
            'OBIORA_PANEL_URL' => 'http://127.0.0.1',
            'OBIORA_SERVER_ID' => '1',
        ]);

        $this->assertStringContainsString('run-security-scan.sh', $executor->lastCommand);
        $this->assertStringNotContainsString('bash -c', $executor->lastCommand);
    }
}

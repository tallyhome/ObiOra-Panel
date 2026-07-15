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

            /** @var list<string> */
            public array $lastArgv = [];

            public function run(string $command, array $options = []): ProcessResult
            {
                $this->lastCommand = $command;
                $this->lastArgv = $options['argv'] ?? [];

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

        $script = base_path('agent/scripts/marketplace-exec.sh');
        if (! is_file($script)) {
            $this->markTestSkipped('marketplace-exec.sh absent.');
        }

        $runner = new PrivilegedScriptRunner($executor);
        $runner->run($script, ['arg'], 10, [
            'OBIORA_APP_PASS' => 'motdepasse1234',
            'OBIORA_APP_USERNAME' => 'admin',
        ]);

        if (PHP_OS_FAMILY === 'Linux' && function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            $this->assertSame(['sudo', '-n', realpath($script) ?: $script, 'arg'], $executor->lastArgv);
        } else {
            $this->assertStringContainsString('env ', $executor->lastCommand);
            $this->assertStringContainsString('OBIORA_APP_PASS=', $executor->lastCommand);
        }
    }

    public function test_run_command_wraps_shell_with_bash_c_when_not_systemctl(): void
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
        $runner->runCommand('echo hello', 30);

        $this->assertStringContainsString('bash -c', $executor->lastCommand);
    }

    public function test_run_security_scan_uses_sudo_argv_on_linux_non_root(): void
    {
        if (PHP_OS_FAMILY !== 'Linux' || ! function_exists('posix_geteuid') || posix_geteuid() === 0) {
            $this->markTestSkipped('Test sudo argv uniquement sur Linux non-root.');
        }

        $script = base_path('agent/scripts/run-security-scan.sh');
        if (! is_file($script)) {
            $this->markTestSkipped('run-security-scan.sh absent.');
        }

        $executor = new class implements SystemExecutorInterface
        {
            /** @var list<string> */
            public array $lastArgv = [];

            public function run(string $command, array $options = []): ProcessResult
            {
                $this->lastArgv = $options['argv'] ?? [];

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
        $runner->run($script, ['__obiora_env', '1', 'OBIORA_PANEL_URL='.base64_encode('http://127.0.0.1')], 300);

        $this->assertSame('sudo', $executor->lastArgv[0] ?? null);
        $this->assertSame('-n', $executor->lastArgv[1] ?? null);
        $this->assertSame(realpath($script) ?: $script, $executor->lastArgv[2] ?? null);
        $this->assertSame('__obiora_env', $executor->lastArgv[3] ?? null);
    }

    public function test_run_command_systemctl_uses_direct_sudo_argv(): void
    {
        if (PHP_OS_FAMILY !== 'Linux' || ! function_exists('posix_geteuid') || posix_geteuid() === 0) {
            $this->markTestSkipped('Test sudo systemctl uniquement sur Linux non-root.');
        }

        $executor = new class implements SystemExecutorInterface
        {
            /** @var list<string> */
            public array $lastArgv = [];

            public function run(string $command, array $options = []): ProcessResult
            {
                $this->lastArgv = $options['argv'] ?? [];

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

        $this->assertSame('sudo', $executor->lastArgv[0] ?? null);
        $this->assertSame('-n', $executor->lastArgv[1] ?? null);
        $this->assertStringContainsString('systemctl', $executor->lastArgv[2] ?? '');
        $this->assertSame('start', $executor->lastArgv[3] ?? null);
        $this->assertSame('obiora-doctor-agent.service', $executor->lastArgv[4] ?? null);
    }
}

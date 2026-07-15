<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SystemExecutorInterface;
use App\DTOs\ProcessResult;
use App\Models\Server;
use App\Services\Diagnostics\LocalDoctorDeployService;
use App\Services\System\PrivilegedScriptRunner;
use App\Support\DoctorInstallHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LocalDoctorDeployServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_deploy_runs_install_script_via_sudo_nopasswd(): void
    {
        $server = Server::factory()->create([
            'agent_token' => str_repeat('b', 64),
        ]);

        $executor = new class implements SystemExecutorInterface
        {
            public string $lastCommand = '';

            public function run(string $command, array $options = []): ProcessResult
            {
                $this->lastCommand = $command;

                return ProcessResult::success('OK: ObiOra Doctor & Suite installés');
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

        $service = new LocalDoctorDeployService(
            $executor,
            new PrivilegedScriptRunner($executor),
            app(DoctorInstallHelper::class),
        );

        $result = $service->deploySuite($server);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('doctor-suite-local.sh', $executor->lastCommand);
        if (PHP_OS_FAMILY === 'Linux') {
            $this->assertStringContainsString('sudo -n', $executor->lastCommand);
        }
        $this->assertStringContainsString('__obiora_env', $executor->lastCommand);
        $this->assertStringNotContainsString('curl', $executor->lastCommand);
    }

    public function test_suite_install_local_args_encode_env_for_sudoers(): void
    {
        $server = Server::factory()->make([
            'id' => 7,
            'agent_token' => 'token-secret',
        ]);

        $args = app(DoctorInstallHelper::class)->suiteInstallLocalArgs($server);

        $this->assertSame('__obiora_env', $args[0]);
        $this->assertSame('7', $args[1]);
        $this->assertStringContainsString('OBIORA_AGENT_TOKEN='.base64_encode('token-secret'), implode(' ', $args));
    }
}

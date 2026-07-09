<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\SshConnection;
use App\Jobs\Diagnostics\DoctorRemoteDeployJob;
use App\Models\Server;
use App\Services\Diagnostics\DoctorDeployProgressService;
use App\Services\Diagnostics\DoctorRemoteDeployService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class DoctorRemoteDeployFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_deploy_dispatches_queue_job_and_starts_progress(): void
    {
        Queue::fake();

        $user = \App\Models\User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        $server = Server::factory()->create([
            'agent_token' => str_repeat('x', 64),
            'metadata' => [
                'ssh_deploy' => [
                    'public_key' => 'ssh-ed25519 AAAATEST',
                    'private_key' => Crypt::encryptString("-----BEGIN OPENSSH PRIVATE KEY-----\ntest\n-----END OPENSSH PRIVATE KEY-----"),
                    'installed_on_remote_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        session(['current_server_id' => $server->id]);

        Livewire::actingAs($user)
            ->test(\Modules\Monitoring\Livewire\DoctorSuiteIndex::class)
            ->set('serverId', $server->id)
            ->set('sshHost', $server->ip_address)
            ->set('sshUser', 'root')
            ->set('sshTestOk', true)
            ->call('deployRemote')
            ->assertSet('deployRunning', true)
            ->assertSet('deployProgress', 5);

        Queue::assertPushed(DoctorRemoteDeployJob::class);

        $status = app(DoctorDeployProgressService::class)->status($server->id);
        $this->assertIsArray($status);
        $this->assertTrue($status['running'] ?? false);
        $this->assertNotEmpty($status['console'] ?? []);
    }

    public function test_deploy_runner_appends_console_logs(): void
    {
        $server = Server::factory()->create();
        $progress = app(DoctorDeployProgressService::class);

        $progress->start($server->id);
        $progress->appendLog($server->id, 'Test ligne console');

        $status = $progress->status($server->id);
        $this->assertStringContainsString('Test ligne console', implode("\n", $status['console'] ?? []));
    }

    public function test_deploy_command_is_registered(): void
    {
        $this->artisan('obiora:doctor-deploy', [
            'serverId' => '999999',
            'host' => '127.0.0.1',
            'port' => '22',
            'user' => 'root',
            'doctor' => 'no',
            'crash' => 'no',
        ])->assertSuccessful();
    }

    public function test_test_connection_returns_structured_result(): void
    {
        $service = app(DoctorRemoteDeployService::class);
        $ssh = new SshConnection('127.0.0.1', 22, 'root', 'invalid');

        $result = $service->testConnection($ssh);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('output', $result);
        $this->assertStringNotContainsString('sshpass', strtolower($result['output']));
    }

    public function test_doctor_suite_page_shows_ssh_workflow(): void
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        Server::factory()->create(['name' => 'VPS Test']);

        $response = $this->actingAs($user)->get(route('doctor.index'));
        $response->assertOk();
        $response->assertSee('Tester la connexion');
        $response->assertSee('Installer sur le serveur');
        $response->assertSee('Comment installer les agents');
    }

    public function test_deploy_progress_service_finish(): void
    {
        $server = Server::factory()->create();
        $progress = app(DoctorDeployProgressService::class);

        $progress->start($server->id);
        $progress->finish($server->id, false, 'Échec test', [['component' => 'test', 'success' => false, 'output' => 'err']]);

        $status = $progress->status($server->id);
        $this->assertFalse($status['success'] ?? true);
        $this->assertSame('Échec test', $status['message'] ?? '');
    }

    public function test_stale_deploy_is_detected(): void
    {
        $server = Server::factory()->create();
        $progress = app(DoctorDeployProgressService::class);

        $progress->start($server->id);
        \Illuminate\Support\Facades\Cache::put(
            $progress->cacheKey($server->id),
            [
                'progress' => 5,
                'message' => 'Bloqué',
                'running' => true,
                'steps' => [],
                'console' => [],
                'updated_at' => now()->subMinutes(5)->toIso8601String(),
            ],
            3600,
        );

        $this->assertTrue($progress->isStale($server->id, 180));
    }
}

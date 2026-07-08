<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\SshConnection;
use App\Jobs\Diagnostics\DoctorRemoteDeployJob;
use App\Models\Server;
use App\Services\Diagnostics\DoctorDeployProgressService;
use App\Services\Diagnostics\DoctorRemoteDeployService;
use App\Services\Diagnostics\ServerSshKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class DoctorRemoteDeployTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_deploy_dispatches_async_job(): void
    {
        Bus::fake();

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

        \Livewire\Livewire::actingAs($user)
            ->test(\Modules\Monitoring\Livewire\DoctorSuiteIndex::class)
            ->set('serverId', $server->id)
            ->set('sshHost', $server->ip_address)
            ->set('sshUser', 'root')
            ->call('deployRemote')
            ->assertSet('deployRunning', true);

        Bus::assertDispatched(DoctorRemoteDeployJob::class);
    }

    public function test_test_connection_returns_structured_result(): void
    {
        $service = app(DoctorRemoteDeployService::class);
        $ssh = new SshConnection('127.0.0.1', 22, 'root', 'invalid');

        $result = $service->testConnection($ssh);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('output', $result);
    }

    public function test_doctor_suite_page_shows_ssh_workflow(): void
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        Server::factory()->create(['name' => 'VPS Test']);

        $response = $this->actingAs($user)->get(route('doctor.index'));
        $response->assertOk();
        $response->assertSee('Générer clé SSH');
        $response->assertSee('Tester connexion SSH');
        $response->assertSee('Générer la clé');
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
}

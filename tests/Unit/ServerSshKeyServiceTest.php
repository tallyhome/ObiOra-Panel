<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Server;
use App\Services\Diagnostics\DoctorDeployProgressService;
use App\Services\Diagnostics\ServerSshKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class ServerSshKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_stores_encrypted_key_in_metadata(): void
    {
        if (! $this->sshKeygenAvailable()) {
            $this->markTestSkipped('ssh-keygen non disponible.');
        }

        $server = Server::factory()->create();
        $service = app(ServerSshKeyService::class);

        $public = $service->generate($server);
        $server->refresh();

        $this->assertStringStartsWith('ssh-ed25519', $public);
        $this->assertNotNull($service->publicKey($server));
        $this->assertNotNull($service->privateKey($server));
        $this->assertFalse($service->isInstalledOnRemote($server));

        $raw = $server->metadata['ssh_deploy']['private_key'] ?? '';
        $this->assertNotSame($service->privateKey($server), $raw);
    }

    public function test_key_applies_to_host_only_when_ip_or_remote_host_matches(): void
    {
        $service = app(ServerSshKeyService::class);

        $server = Server::factory()->create([
            'ip_address' => '54.37.103.241',
            'metadata' => [
                'ssh_deploy' => [
                    'public_key' => 'ssh-ed25519 AAAATEST',
                    'private_key' => Crypt::encryptString("-----BEGIN OPENSSH PRIVATE KEY-----\ntest\n-----END OPENSSH PRIVATE KEY-----"),
                    'installed_on_remote_at' => now()->toIso8601String(),
                ],
                'doctor_deploy' => [
                    'remote_host' => '54.37.103.241',
                ],
            ],
        ]);

        $this->assertTrue($service->keyAppliesToHost($server, '54.37.103.241'));
        $this->assertFalse($service->keyAppliesToHost($server, '141.94.131.143'));
    }

    public function test_deploy_progress_cache_lifecycle(): void
    {
        $progress = app(DoctorDeployProgressService::class);
        $serverId = 42;

        $progress->start($serverId);
        $status = $progress->status($serverId);

        $this->assertTrue($status['running'] ?? false);
        $this->assertSame(5, $status['progress'] ?? 0);

        $progress->update($serverId, 50, 'Mi-parcours', []);
        $this->assertSame(50, $progress->status($serverId)['progress'] ?? 0);

        $progress->finish($serverId, true, 'OK', [], 'log');
        $final = $progress->status($serverId);
        $this->assertFalse($final['running'] ?? true);
        $this->assertTrue($final['success'] ?? false);

        Cache::forget($progress->cacheKey($serverId));
    }

    private function sshKeygenAvailable(): bool
    {
        $process = new \Symfony\Component\Process\Process(['which', 'ssh-keygen']);
        $process->run();

        return $process->isSuccessful();
    }
}

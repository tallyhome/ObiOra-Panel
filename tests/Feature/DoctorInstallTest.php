<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class DoctorInstallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_bootstrap_script_is_publicly_accessible(): void
    {
        $response = $this->get(route('install.doctor-agent'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/x-shellscript; charset=utf-8');
        $this->assertStringContainsString('obiora-doctor-agent', $response->getContent());
    }

    public function test_install_command_api_returns_real_token(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        $server = Server::factory()->create([
            'agent_token' => 'test-agent-token-abc',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('monitoring.api.install-command', $server));

        $response->assertOk();
        $response->assertJsonPath('server_id', $server->id);
        $this->assertStringContainsString('test-agent-token-abc', (string) $response->json('remote'));
        $this->assertStringContainsString('/install/doctor-agent.sh', (string) $response->json('remote'));
    }
}

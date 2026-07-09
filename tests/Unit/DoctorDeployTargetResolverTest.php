<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\Diagnostics\DoctorDeployTargetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DoctorDeployTargetResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_server_by_ssh_ip(): void
    {
        $existing = Server::factory()->create(['ip_address' => '10.0.0.5', 'name' => 'Slave A']);

        $resolved = app(DoctorDeployTargetResolver::class)->resolve('10.0.0.5');

        $this->assertSame($existing->id, $resolved->id);
    }

    public function test_creates_server_when_ip_unknown(): void
    {
        $resolver = app(DoctorDeployTargetResolver::class);

        $resolved = $resolver->resolve('10.0.0.99', 'remote-host.example');

        $this->assertSame('10.0.0.99', $resolved->ip_address);
        $this->assertDatabaseHas('servers', ['ip_address' => '10.0.0.99']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketplaceInstallSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_filebrowser_install_options(): void
    {
        $this->seed();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');
        $server = Server::query()->where('is_master', true)->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession(['current_server_id' => $server->id])
            ->post(route('plugins.install-setup'), [
                'slug' => 'filebrowser',
                'label' => 'Mon File Browser',
                'username' => 'admin',
                'pass' => 'motdepasse1234',
            ]);

        $response->assertRedirect(route('plugins.index'));
        $response->assertSessionHas('success');
        $response->assertSessionMissing('error');
    }

    public function test_rejects_empty_filebrowser_password(): void
    {
        $this->seed();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');
        $server = Server::query()->where('is_master', true)->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession(['current_server_id' => $server->id])
            ->post(route('plugins.install-setup'), [
                'slug' => 'filebrowser',
                'label' => 'Mon File Browser',
                'username' => 'admin',
                'pass' => '',
            ]);

        $response->assertRedirect(route('plugins.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHas('install_setup_slug', 'filebrowser');
    }
}

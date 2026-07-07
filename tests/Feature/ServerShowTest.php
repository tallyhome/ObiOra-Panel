<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ServerShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_show_page_renders_for_master(): void
    {
        $this->seed();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        $server = Server::query()->where('is_master', true)->firstOrFail();

        $this->actingAs($user)
            ->get(route('servers.show', $server))
            ->assertOk()
            ->assertSee('Informations')
            ->assertSee($server->name);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class SecurityModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_security_page_requires_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('security.index'))
            ->assertForbidden();
    }

    public function test_security_page_loads_for_modules_view(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        Server::factory()->master()->create(['name' => 'Panel Master']);

        $this->actingAs($user)
            ->get(route('security.index'))
            ->assertOk()
            ->assertSee('Sécurité serveur');
    }
}

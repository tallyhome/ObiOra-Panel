<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class InfrastructureModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_infrastructure_routes_require_auth(): void
    {
        $routes = [
            'ssl.index',
            'firewall.index',
            'users.index',
            'profile.index',
            'doctor.index',
        ];

        foreach ($routes as $route) {
            $response = $this->get(route($route));
            $response->assertRedirect();
            $this->assertContains(
                $response->headers->get('Location'),
                [route('login'), route('setup')],
            );
        }
    }

    public function test_authenticated_user_can_open_infrastructure_modules(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        $this->actingAs($user)
            ->get(route('ssl.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('profile.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('doctor.index'))
            ->assertOk();
    }
}

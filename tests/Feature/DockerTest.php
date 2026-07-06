<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DockerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_docker_index(): void
    {
        $this->seed();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        $this->actingAs($user)->get('/docker')->assertOk();
    }
}

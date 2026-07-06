<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SetupFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_page_is_shown_when_no_admin(): void
    {
        $this->seed();

        $this->get('/setup')->assertOk();
    }

    public function test_dashboard_redirects_to_setup_without_admin(): void
    {
        $this->seed();

        $this->get('/dashboard')->assertRedirect('/setup');
    }

    public function test_authenticated_admin_can_access_dashboard(): void
    {
        $this->seed();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Demo\DemoAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DemoAccountBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_account_can_be_created_without_prior_role_seeder(): void
    {
        $account = app(DemoAccountService::class)->create('visitor@example.com', 'Visiteur', 1);

        $this->assertSame('visitor@example.com', $account['email']);
        $this->assertDatabaseHas('users', [
            'email' => 'visitor@example.com',
            'is_demo' => true,
        ]);
        $this->assertTrue(
            \App\Models\User::query()->where('email', 'visitor@example.com')->first()?->hasRole('client') ?? false
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        User::factory()->create();
    }

    public function test_locale_switch_sets_cookie_and_translates_login(): void
    {
        $this->get(route('locale', 'en'))
            ->assertRedirect()
            ->assertCookie('obiora_locale', 'en');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in', false)
            ->assertSee('Password', false);
    }

    public function test_french_login_by_default(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Connexion', false)
            ->assertSee('Mot de passe', false);
    }
}

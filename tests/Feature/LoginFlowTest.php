<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use App\Support\PanelDatabase;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

final class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_valid_login_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.io',
            'password' => Hash::make('secret-password'),
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');

        Livewire::test(Login::class)
            ->set('email', 'admin@test.io')
            ->set('password', 'secret-password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_page_returns_service_unavailable_when_database_unreachable(): void
    {
        $originalDefault = (string) config('database.default');

        try {
            Config::set('database.default', 'broken');
            Config::set('database.connections.broken', [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '59999',
                'database' => 'obiora',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]);
            DB::purge('broken');
            PanelDatabase::resetCache();

            $this->get(route('login'))
                ->assertStatus(503)
                ->assertSee('démarrage en cours', false);
        } finally {
            Config::set('database.default', $originalDefault);
            DB::purge('broken');
            DB::purge($originalDefault);
            PanelDatabase::resetCache();
        }
    }
}

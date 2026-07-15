<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use App\Services\Demo\DemoAccountService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

final class DemoAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['obiora.site_api.key' => 'test-site-api-key']);
    }

    public function test_site_api_creates_demo_client_with_docker_access(): void
    {
        $response = $this->withToken('test-site-api-key')
            ->postJson('/api/v1/demo-accounts', [
                'email' => 'visitor@example.com',
                'name' => 'Visiteur',
                'ttl_hours' => 24,
            ]);

        $response->assertCreated()
            ->assertJsonPath('email', 'visitor@example.com')
            ->assertJsonStructure(['login_url']);

        $this->assertStringContainsString('/demo/enter/', (string) $response->json('login_url'));

        $user = User::query()->findOrFail($response->json('user_id'));
        $this->assertTrue($user->is_demo);
        $this->assertTrue($user->hasRole('client'));
        $this->assertTrue($user->can('docker.manage'));
        $this->assertNotNull($user->demo_expires_at);
    }

    public function test_expired_demo_cannot_login(): void
    {
        $user = User::factory()->create([
            'is_demo' => true,
            'is_active' => true,
            'demo_expires_at' => now()->subHour(),
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('client');

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_expired_demo_session_is_logged_out(): void
    {
        $user = User::factory()->create([
            'is_demo' => true,
            'demo_expires_at' => now()->subMinutes(5),
        ]);
        $user->assignRole('client');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_expire_command_deletes_due_demo_accounts(): void
    {
        $service = app(DemoAccountService::class);
        $service->create('old-demo@test.io', 'Old', 1);

        $user = User::query()->where('email', 'old-demo@test.io')->firstOrFail();
        $user->update(['demo_expires_at' => now()->subHour()]);

        Artisan::call('obiora:expire-demo-accounts');

        $this->assertDatabaseMissing('users', ['email' => 'old-demo@test.io']);
    }
}

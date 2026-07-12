<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

final class PostLoginReconnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_reconnect_login_then_dashboard_returns_ok(): void
    {
        $user = $this->makeAdmin();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'secret-password')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        Auth::logout();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'secret-password')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_reconnect_login_with_intended_doctor_page_returns_ok(): void
    {
        $user = $this->makeAdmin();
        Server::factory()->create(['is_master' => true, 'name' => 'Master']);

        $this->get(route('doctor.index'))->assertRedirect(route('login'));

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'secret-password')
            ->call('login')
            ->assertRedirect(route('doctor.index'));

        $this->actingAs($user)->get(route('doctor.index'))->assertOk();
    }

    public function test_doctor_page_with_open_journal_poll_does_not_error(): void
    {
        $user = $this->makeAdmin();
        $server = Server::factory()->create(['is_master' => true]);

        Livewire::actingAs($user)
            ->test(\Modules\Monitoring\Livewire\DoctorSuiteIndex::class, ['serverId' => $server->id])
            ->set('panelJournalOpen', true)
            ->call('$refresh')
            ->assertOk();
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create([
            'email' => 'admin@test.io',
            'password' => Hash::make('secret-password'),
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');

        return $user;
    }
}

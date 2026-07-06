<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DatabaseStatus;
use App\Models\ManagedDatabase;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_databases_index(): void
    {
        $this->seed();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        $this->actingAs($user)->get('/databases')->assertOk();
    }

    public function test_managed_database_belongs_to_server(): void
    {
        $this->seed();

        $server = Server::query()->where('is_master', true)->firstOrFail();

        $database = ManagedDatabase::query()->create([
            'server_id' => $server->id,
            'name' => 'test_app',
            'username' => 'test_app_user',
            'password' => 'secretpassword12',
            'status' => DatabaseStatus::Active,
        ]);

        $this->assertTrue($database->server->is($server));
        $this->assertSame('secretpassword12', $database->password_plain);
    }
}

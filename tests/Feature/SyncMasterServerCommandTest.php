<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SyncMasterServerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_master_creates_master_server(): void
    {
        $this->assertDatabaseCount('servers', 0);

        $this->artisan('obiora:sync-master')
            ->assertSuccessful();

        $this->assertDatabaseHas('servers', ['is_master' => true]);
        $this->assertNotNull(Server::query()->where('is_master', true)->value('agent_token'));
    }
}

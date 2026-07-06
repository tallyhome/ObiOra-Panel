<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_backups_index(): void
    {
        $this->seed();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        $this->actingAs($user)->get('/backups')->assertOk();
    }

    public function test_backup_belongs_to_server(): void
    {
        $this->seed();

        $server = Server::query()->where('is_master', true)->firstOrFail();

        $backup = Backup::query()->create([
            'server_id' => $server->id,
            'name' => 'test-backup',
            'type' => BackupType::Database,
            'filename' => 'test.sql.gz',
            'storage_path' => '/var/backups/obiora/test.sql.gz',
            'size_bytes' => 2048,
            'status' => BackupStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->assertTrue($backup->server->is($server));
        $this->assertSame('2 Ko', $backup->humanSize());
    }
}

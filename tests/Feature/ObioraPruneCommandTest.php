<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ServerPingSample;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ObioraPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_dry_run_lists_ping_samples(): void
    {
        config(['monitoring.retention_days' => 30]);

        $server = Server::factory()->create();
        ServerPingSample::query()->create([
            'server_id' => $server->id,
            'latency_ms' => 10,
            'success' => true,
            'sampled_at' => now()->subDays(45),
        ]);

        $this->artisan('obiora:prune', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('server_ping_samples');
    }

    public function test_ping_prune_job_deletes_old_samples(): void
    {
        config(['monitoring.retention_days' => 30]);

        $server = Server::factory()->create();
        ServerPingSample::query()->create([
            'server_id' => $server->id,
            'latency_ms' => 10,
            'success' => true,
            'sampled_at' => now()->subDays(45),
        ]);
        ServerPingSample::query()->create([
            'server_id' => $server->id,
            'latency_ms' => 5,
            'success' => true,
            'sampled_at' => now()->subDays(5),
        ]);

        (new \App\Jobs\PruneOldServerPingSamplesJob)->handle();

        $this->assertDatabaseCount('server_ping_samples', 1);
    }
}

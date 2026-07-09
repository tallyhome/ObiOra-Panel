<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\UpdateHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpdateProgressCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_clamps_progress_between_0_and_100(): void
    {
        $history = UpdateHistory::query()->create([
            'from_version' => '2.1.30',
            'to_version' => '2.1.31',
            'status' => 'running',
            'progress' => 10,
            'progress_message' => 'Démarrage',
        ]);

        $this->artisan('obiora:update-progress', [
            'historyId' => $history->id,
            'progress' => 150,
            'message' => 'Presque fini',
        ])->assertSuccessful();

        $history->refresh();
        $this->assertSame(100, $history->progress);
        $this->assertSame('Presque fini', $history->progress_message);
    }

    public function test_returns_failure_for_unknown_history(): void
    {
        $this->artisan('obiora:update-progress', [
            'historyId' => 99999,
            'progress' => 50,
            'message' => 'Test',
        ])->assertFailed();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\UpdateHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class CompleteUpdateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_history_completed_and_removes_lock(): void
    {
        $lock = storage_path('framework/obiora-update.lock');
        File::ensureDirectoryExists(dirname($lock));
        File::put($lock, now()->toIso8601String());

        $history = UpdateHistory::query()->create([
            'from_version' => '2.9.0',
            'to_version' => '3.0.0',
            'status' => 'running',
            'progress' => 100,
            'progress_message' => 'Mise à jour terminée avec succès',
        ]);

        $this->artisan('obiora:update-complete', [
            'historyId' => $history->id,
            'status' => 'completed',
            '--message' => 'Mise à jour terminée avec succès',
        ])->assertSuccessful();

        $history->refresh();
        $this->assertSame('completed', $history->status);
        $this->assertSame(100, $history->progress);
        $this->assertNotNull($history->completed_at);
        $this->assertFalse(File::exists($lock));
    }
}

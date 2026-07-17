<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\ApplyPanelUpdateJob;
use App\Models\UpdateHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApplyPanelUpdateJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_marks_only_queued_or_running_histories(): void
    {
        $queued = UpdateHistory::query()->create([
            'from_version' => '2.1.30',
            'to_version' => '2.1.31',
            'status' => 'queued',
            'progress' => 2,
            'progress_message' => 'En attente',
        ]);

        $completed = UpdateHistory::query()->create([
            'from_version' => '2.1.29',
            'to_version' => '2.1.30',
            'status' => 'completed',
            'progress' => 100,
            'progress_message' => 'OK',
            'completed_at' => now(),
        ]);

        $job = new ApplyPanelUpdateJob($queued->id);
        $job->failed(new \RuntimeException('timeout'));

        $this->assertSame('failed', $queued->fresh()->status);
        $this->assertSame('completed', $completed->fresh()->status);
        $this->assertStringContainsString('récupération HTTP', (string) $queued->fresh()->progress_message);
    }
}

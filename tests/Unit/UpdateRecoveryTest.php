<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\UpdateHistory;
use App\Services\Core\UpdateRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpdateRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_stale_running_updates_as_failed(): void
    {
        $history = UpdateHistory::query()->create([
            'from_version' => '2.0.1',
            'to_version' => '2.1.0',
            'status' => 'running',
            'progress' => 58,
            'progress_message' => 'Compilation des assets frontend…',
        ]);
        $history->forceFill(['updated_at' => now()->subHours(2)])->save();

        $count = $this->app->make(UpdateRecovery::class)->recoverStale(40);

        $this->assertSame(1, $count);
        $this->assertSame('failed', $history->fresh()->status);
    }
}

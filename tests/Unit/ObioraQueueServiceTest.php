<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SystemExecutorInterface;
use App\DTOs\ProcessResult;
use App\Services\Core\ObioraQueueService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ObioraQueueServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        File::delete(storage_path('framework/obiora-queue.version'));
        parent::tearDown();
    }

    public function test_skips_reload_when_worker_marker_matches_panel_version(): void
    {
        File::put(storage_path('framework/obiora-queue.version'), trim((string) file_get_contents(base_path('VERSION'))));

        $executor = new class implements SystemExecutorInterface
        {
            public int $runCount = 0;

            public function run(string $command, array $options = []): ProcessResult
            {
                $this->runCount++;

                return ProcessResult::success('active');
            }

            public function runScript(string $path, array $args = []): ProcessResult
            {
                return ProcessResult::success();
            }

            public function runAsUser(string $user, string $command, array $options = []): ProcessResult
            {
                return ProcessResult::success();
            }
        };

        $service = new ObioraQueueService($executor);

        if (PHP_OS_FAMILY === 'Linux') {
            $this->assertTrue($service->ensureFreshWorker());
            $this->assertSame(1, $executor->runCount);
        } else {
            $this->assertTrue($service->ensureFreshWorker());
            $this->assertSame(0, $executor->runCount);
        }
    }

    public function test_reloads_worker_when_panel_version_changed(): void
    {
        File::put(storage_path('framework/obiora-queue.version'), '0.0.0');

        $executor = new class implements SystemExecutorInterface
        {
            /** @var list<string> */
            public array $commands = [];

            public function run(string $command, array $options = []): ProcessResult
            {
                $this->commands[] = $command;

                return ProcessResult::success(str_contains($command, 'is-active') ? 'active' : '');
            }

            public function runScript(string $path, array $args = []): ProcessResult
            {
                return ProcessResult::success();
            }

            public function runAsUser(string $user, string $command, array $options = []): ProcessResult
            {
                return ProcessResult::success();
            }
        };

        $service = new ObioraQueueService($executor);

        if (PHP_OS_FAMILY === 'Linux') {
            $this->assertTrue($service->ensureFreshWorker());
            $this->assertTrue(collect($executor->commands)->contains(fn (string $cmd) => str_contains($cmd, 'restart obiora-queue')));
            $this->assertSame(trim((string) file_get_contents(base_path('VERSION'))), trim((string) file_get_contents(storage_path('framework/obiora-queue.version'))));
        } else {
            $this->assertTrue($service->ensureFreshWorker());
        }
    }
}

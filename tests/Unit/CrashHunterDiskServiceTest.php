<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SystemExecutorInterface;
use App\DTOs\ProcessResult;
use App\Services\CrashHunter\CrashHunterDiskService;
use App\Services\System\PrivilegedScriptRunner;
use Tests\TestCase;

final class CrashHunterDiskServiceTest extends TestCase
{
    public function test_audit_local_returns_structure(): void
    {
        $executor = new class implements SystemExecutorInterface
        {
            public function run(string $command, array $options = []): ProcessResult
            {
                return ProcessResult::failure('skip');
            }

            public function runScript(string $path, array $args = []): ProcessResult
            {
                return ProcessResult::failure('skip');
            }

            public function runAsUser(string $user, string $command, array $options = []): ProcessResult
            {
                return ProcessResult::failure('skip');
            }
        };

        $service = new CrashHunterDiskService(new PrivilegedScriptRunner($executor));
        $audit = $service->auditLocal();

        $this->assertArrayHasKey('total_bytes', $audit);
        $this->assertArrayHasKey('bundle_count', $audit);
        $this->assertArrayHasKey('path', $audit);
    }

    public function test_normalize_marks_large_usage_as_warning(): void
    {
        $executor = new class implements SystemExecutorInterface
        {
            public function run(string $command, array $options = []): ProcessResult
            {
                return ProcessResult::success();
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

        $service = new CrashHunterDiskService(new PrivilegedScriptRunner($executor));
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('normalizeAudit');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'path' => '/opt/crashhunter',
            'total_bytes' => 10 * 1024 * 1024 * 1024,
            'bundles_bytes' => 9 * 1024 * 1024 * 1024,
            'reports_bytes' => 0,
            'logs_bytes' => 0,
            'data_bytes' => 0,
            'bundle_count' => 100,
            'report_count' => 0,
        ]);

        $this->assertTrue($result['warning']);
        $this->assertStringContainsString('Go', $result['total_human']);
    }
}

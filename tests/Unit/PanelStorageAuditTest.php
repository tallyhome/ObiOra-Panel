<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PanelStorageAudit;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class PanelStorageAuditTest extends TestCase
{
    public function test_audit_structure(): void
    {
        $result = (new PanelStorageAudit)->audit();

        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('database_bytes', $result);
        $this->assertArrayHasKey('total_bytes', $result);
        $this->assertNotEmpty($result['paths']);
        $this->assertArrayHasKey('label', $result['paths'][0]);
        $this->assertArrayHasKey('bytes', $result['paths'][0]);
    }

    public function test_clear_compiled_views_deletes_files(): void
    {
        $viewsDir = storage_path('framework/views');
        File::ensureDirectoryExists($viewsDir);
        $testFile = $viewsDir.'/audit-test.php';
        File::put($testFile, '<?php');

        $deleted = (new PanelStorageAudit)->clearCompiledViews();

        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertFileDoesNotExist($testFile);
    }
}

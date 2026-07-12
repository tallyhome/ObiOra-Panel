<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Monitor;
use App\Services\Monitoring\MonitorImportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MonitorImportExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_skips_duplicate_names(): void
    {
        Monitor::factory()->create(['name' => 'Existing']);

        $service = app(MonitorImportExportService::class);
        $result = $service->importJson([
            'monitors' => [
                ['name' => 'Existing', 'type' => 'https', 'target' => 'https://a.test'],
                ['name' => 'New Site', 'type' => 'https', 'target' => 'https://b.test'],
            ],
        ]);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseHas('monitors', ['name' => 'New Site']);
    }

    public function test_export_json_excludes_secrets(): void
    {
        Monitor::factory()->create(['name' => 'Site A', 'target' => 'https://a.test']);

        $export = app(MonitorImportExportService::class)->exportJson();

        $this->assertSame(1, $export['version']);
        $this->assertCount(1, $export['monitors']);
        $this->assertArrayNotHasKey('track_token', $export['monitors'][0]);
    }
}

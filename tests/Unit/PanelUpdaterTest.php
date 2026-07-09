<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SystemExecutorInterface;
use App\Models\UpdateHistory;
use App\Services\Core\PanelUpdater;
use App\Services\Core\UpdateManager;
use App\Support\InstalledVersion;
use App\Support\PanelUpdateIntegrity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PanelUpdaterTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_update_refuses_when_integrity_check_fails(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('MAJ panel uniquement sur Linux.');
        }

        $integrity = $this->createMock(PanelUpdateIntegrity::class);
        $integrity->method('verify')->willReturn([
            'ok' => false,
            'missing' => ['install/update-panel.sh'],
            'warnings' => [],
        ]);

        $this->app->instance(PanelUpdateIntegrity::class, $integrity);

        $updater = $this->app->make(PanelUpdater::class);
        $result = $updater->queueUpdate();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('intégrité', strtolower($result['message']));
    }

    public function test_queue_update_refuses_when_no_release_available(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('MAJ panel uniquement sur Linux.');
        }

        file_put_contents(base_path('VERSION'), "99.0.0\n");

        Http::fake([
            'api.github.com/repos/*/releases/latest' => Http::response([
                'tag_name' => 'v99.0.0',
                'html_url' => 'https://example.com/release',
            ]),
        ]);

        $updater = $this->app->make(PanelUpdater::class);
        $result = $updater->queueUpdate();

        $this->assertFalse($result['success']);

        @unlink(base_path('VERSION'));
    }
}

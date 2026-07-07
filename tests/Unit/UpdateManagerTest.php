<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Core\UpdateManager;
use App\Support\InstalledVersion;
use App\Support\VersionComparator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class UpdateManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_update_when_github_release_is_newer(): void
    {
        file_put_contents(base_path('VERSION'), "1.8.9\n");

        Http::fake([
            'api.github.com/repos/*/releases/latest' => Http::response([
                'tag_name' => 'v1.9.2',
                'html_url' => 'https://github.com/tallyhome/ObiOra-Panel/releases/tag/v1.9.2',
            ]),
        ]);

        $manager = new UpdateManager(new VersionComparator, new InstalledVersion);
        $result = $manager->checkForUpdates(fresh: true);

        $this->assertTrue($result['available']);
        $this->assertSame('1.9.2', $result['latest']);
        $this->assertSame('1.8.9', $result['current']);

        @unlink(base_path('VERSION'));
    }

    public function test_falls_back_to_releases_list_when_latest_endpoint_fails(): void
    {
        file_put_contents(base_path('VERSION'), "1.8.9\n");

        Http::fake([
            'api.github.com/repos/*/releases/latest' => Http::response([], 404),
            'api.github.com/repos/*/releases*' => Http::response([
                [
                    'tag_name' => 'v1.9.1',
                    'draft' => false,
                    'prerelease' => false,
                    'html_url' => 'https://github.com/tallyhome/ObiOra-Panel/releases/tag/v1.9.1',
                ],
            ]),
        ]);

        $manager = new UpdateManager(new VersionComparator, new InstalledVersion);
        $result = $manager->checkForUpdates(fresh: true);

        $this->assertTrue($result['available']);
        $this->assertSame('1.9.1', $result['latest']);

        @unlink(base_path('VERSION'));
    }
}

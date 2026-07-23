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

    private string $originalVersion = '';

    protected function setUp(): void
    {
        parent::setUp();
        $path = base_path('VERSION');
        $this->originalVersion = is_readable($path) ? (string) file_get_contents($path) : "4.0.23\n";
    }

    protected function tearDown(): void
    {
        file_put_contents(base_path('VERSION'), $this->originalVersion !== '' ? $this->originalVersion : "4.0.23\n");
        parent::tearDown();
    }

    public function test_detects_update_when_github_release_is_newer(): void
    {
        file_put_contents(base_path('VERSION'), "1.8.9\n");

        Http::fake([
            'api.github.com/repos/*/releases/latest' => Http::response([
                'tag_name' => 'v1.9.2',
                'html_url' => 'https://github.com/tallyhome/ObiOra-Panel/releases/tag/v1.9.2',
            ]),
            'api.github.com/repos/*/tags*' => Http::response([
                ['name' => 'v1.9.2'],
            ]),
        ]);

        $manager = new UpdateManager(new VersionComparator, new InstalledVersion);
        $result = $manager->checkForUpdates(fresh: true);

        $this->assertTrue($result['available']);
        $this->assertSame('1.9.2', $result['latest']);
        $this->assertSame('1.8.9', $result['current']);
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
            'api.github.com/repos/*/tags*' => Http::response([
                ['name' => 'v1.9.0'],
            ]),
        ]);

        $manager = new UpdateManager(new VersionComparator, new InstalledVersion);
        $result = $manager->checkForUpdates(fresh: true);

        $this->assertTrue($result['available']);
        $this->assertSame('1.9.1', $result['latest']);
    }

    public function test_prefers_newer_git_tag_over_stale_github_latest_release(): void
    {
        file_put_contents(base_path('VERSION'), "4.0.20\n");

        Http::fake([
            'api.github.com/repos/*/releases/latest' => Http::response([
                'tag_name' => 'v2.1.38',
                'html_url' => 'https://github.com/tallyhome/ObiOra-Panel/releases/tag/v2.1.38',
            ]),
            'api.github.com/repos/*/tags*' => Http::response([
                ['name' => 'v4.0.22'],
                ['name' => 'v4.0.21'],
                ['name' => 'v2.1.38'],
            ]),
        ]);

        $manager = new UpdateManager(new VersionComparator, new InstalledVersion);
        $result = $manager->checkForUpdates(fresh: true);

        $this->assertSame('4.0.22', $result['latest']);
        $this->assertTrue($result['available']);
        $this->assertFalse(
            version_compare((string) $result['latest'], (string) $result['current'], '<'),
            'latest ne doit jamais être un downgrade'
        );
    }
}

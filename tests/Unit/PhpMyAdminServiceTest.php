<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Database\PhpMyAdminService;
use Tests\TestCase;

final class PhpMyAdminServiceTest extends TestCase
{
    public function test_configured_url_takes_precedence(): void
    {
        config([
            'obiora.databases.phpmyadmin_url' => 'https://pma.example.com/',
            'obiora.databases.phpmyadmin_port' => 8099,
        ]);

        $status = app(PhpMyAdminService::class)->status(null);

        $this->assertSame('configured', $status['status']);
        $this->assertSame('https://pma.example.com', $status['url']);
    }

    public function test_package_manifest_and_scripts_exist(): void
    {
        $this->assertFileExists(base_path('packages/phpmyadmin/manifest.json'));
        $this->assertFileExists(base_path('packages/phpmyadmin/install.sh'));
        $this->assertFileExists(base_path('packages/phpmyadmin/uninstall.sh'));
        $this->assertFileExists(base_path('agent/scripts/phpmyadmin-ensure.sh'));
        $this->assertFileExists(base_path('agent/scripts/phpmyadmin-status.sh'));

        $manifest = json_decode((string) file_get_contents(base_path('packages/phpmyadmin/manifest.json')), true);
        $this->assertIsArray($manifest);
        $this->assertSame('phpmyadmin', $manifest['slug']);
        $this->assertSame('latest', $manifest['version']);
        $this->assertSame(8099, $manifest['runtime']['port']);

        $install = (string) file_get_contents(base_path('packages/phpmyadmin/install.sh'));
        $this->assertStringContainsString('phpmyadmin:latest', $install);
        $this->assertStringContainsString('PMA_HOST', $install);
    }
}

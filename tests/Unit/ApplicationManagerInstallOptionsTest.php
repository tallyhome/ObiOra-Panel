<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\ApplicationPackage;
use App\Services\Applications\ApplicationManager;
use InvalidArgumentException;
use Tests\TestCase;

final class ApplicationManagerInstallOptionsTest extends TestCase
{
    private function manager(): ApplicationManager
    {
        return $this->app->make(ApplicationManager::class);
    }

    private function fileBrowserPackage(): ApplicationPackage
    {
        $manifest = json_decode(
            (string) file_get_contents(base_path('packages/filebrowser/manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return new ApplicationPackage('filebrowser', base_path('packages/filebrowser'), $manifest);
    }

    public function test_validates_filebrowser_password(): void
    {
        $validated = $this->manager()->validateInstallOptions($this->fileBrowserPackage(), [
            'label' => 'File Browser',
            'username' => 'admin',
            'pass' => 'secret123456',
        ]);

        $this->assertSame('secret123456', $validated['pass']);
    }

    public function test_rejects_short_filebrowser_password(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager()->validateInstallOptions($this->fileBrowserPackage(), [
            'label' => 'File Browser',
            'username' => 'admin',
            'pass' => 'court',
        ]);
    }

    public function test_rejects_missing_password(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mot de passe');

        $this->manager()->validateInstallOptions($this->fileBrowserPackage(), [
            'label' => 'File Browser',
            'username' => 'admin',
            'pass' => '',
        ]);
    }

    public function test_nextcloud_auto_provisions_database_from_config(): void
    {
        $package = new ApplicationPackage('nextcloud', base_path('packages/nextcloud'), [
            'name' => 'Nextcloud',
            'slug' => 'nextcloud',
        ]);

        $this->assertTrue($package->databaseAutoProvision());
        $this->assertSame('nextcloud', $package->databaseNamePrefix());
        $this->assertTrue($package->needsInstallWizard());
    }
}

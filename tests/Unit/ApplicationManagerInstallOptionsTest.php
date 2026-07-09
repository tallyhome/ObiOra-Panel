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

    public function test_accepts_short_filebrowser_password(): void
    {
        $validated = $this->manager()->validateInstallOptions($this->fileBrowserPackage(), [
            'label' => 'File Browser',
            'username' => 'admin',
            'pass' => 'court',
        ]);

        $this->assertSame('court', $validated['pass']);
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

    public function test_encode_remote_install_env_for_slave_marketplace(): void
    {
        $encoded = $this->manager()->encodeRemoteInstallEnv([
            'username' => 'admin',
            'pass' => 'secret123456',
            'db_name' => 'nextcloud',
            'db_user' => 'nc_user',
            'db_pass' => 'dbpass',
            'db_host' => 'host.docker.internal',
        ]);

        $this->assertSame(base64_encode('admin'), $encoded['OBIORA_APP_USERNAME']);
        $this->assertSame(base64_encode('secret123456'), $encoded['OBIORA_APP_PASS']);
        $this->assertSame(base64_encode('nextcloud'), $encoded['OBIORA_APP_DB_NAME']);
        $this->assertSame(base64_encode('nc_user'), $encoded['OBIORA_APP_DB_USER']);
        $this->assertSame(base64_encode('dbpass'), $encoded['OBIORA_APP_DB_PASS']);
        $this->assertSame(base64_encode('host.docker.internal'), $encoded['OBIORA_APP_DB_HOST']);
    }
}

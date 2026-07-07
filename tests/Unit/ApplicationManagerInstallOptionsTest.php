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

    public function test_validates_matching_passwords(): void
    {
        $validated = $this->manager()->validateInstallOptions($this->fileBrowserPackage(), [
            'label' => 'File Browser',
            'username' => 'admin',
            'pass' => 'secret123',
            'pass2' => 'secret123',
        ]);

        $this->assertSame('secret123', $validated['pass']);
        $this->assertArrayNotHasKey('pass2', $validated);
    }

    public function test_rejects_missing_password_confirmation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Confirmer le mot de passe');

        $this->manager()->validateInstallOptions($this->fileBrowserPackage(), [
            'label' => 'File Browser',
            'username' => 'admin',
            'pass' => 'secret123',
            'pass2' => '',
        ]);
    }

    public function test_rejects_mismatched_passwords(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Les mots de passe ne correspondent pas.');

        $this->manager()->validateInstallOptions($this->fileBrowserPackage(), [
            'label' => 'File Browser',
            'username' => 'admin',
            'pass' => 'secret123',
            'pass2' => 'other123',
        ]);
    }
}

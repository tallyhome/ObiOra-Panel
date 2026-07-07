<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\ApplicationPackage;
use App\Support\ApplicationIcon;
use Tests\TestCase;

final class ApplicationIconTest extends TestCase
{
    public function test_uses_homarr_cdn_for_known_app(): void
    {
        $package = new ApplicationPackage('nextcloud', base_path('packages/nextcloud'), [
            'name' => 'Nextcloud',
            'slug' => 'nextcloud',
        ]);

        $url = (new ApplicationIcon)->url($package);

        $this->assertStringContainsString('dashboard-icons', $url);
        $this->assertStringContainsString('nextcloud.svg', $url);
    }

    public function test_uses_alias_for_legacy_slug(): void
    {
        $package = new ApplicationPackage('plexpy', base_path('packages/plexpy'), [
            'name' => 'Tautulli',
            'slug' => 'plexpy',
        ]);

        $url = (new ApplicationIcon)->url($package);

        $this->assertStringContainsString('tautulli.svg', $url);
    }

    public function test_fallback_data_uri_contains_first_letter(): void
    {
        $package = new ApplicationPackage('filebrowser', base_path('packages/filebrowser'), [
            'name' => 'File Browser',
            'slug' => 'filebrowser',
        ]);

        $uri = (new ApplicationIcon)->fallbackDataUri($package);

        $decoded = base64_decode(str_replace('data:image/svg+xml;base64,', '', $uri), true);

        $this->assertIsString($decoded);
        $this->assertStringContainsString('>F</text>', $decoded);
    }
}

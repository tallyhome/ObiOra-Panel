<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\ApplicationPackage;
use Tests\TestCase;

final class ApplicationPackageRuntimeTest extends TestCase
{
    public function test_infers_systemd_runtime_for_webmin(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(base_path('packages/webmin/manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $package = new ApplicationPackage('webmin', base_path('packages/webmin'), $manifest);

        $this->assertSame('systemd', $package->effectiveRuntimeType());
        $this->assertSame('webmin', $package->effectiveSystemdService());
    }

    public function test_infers_docker_runtime_for_generated_package(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(base_path('packages/radarr/manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $package = new ApplicationPackage('radarr', base_path('packages/radarr'), $manifest);

        $this->assertSame('docker', $package->effectiveRuntimeType());
        $this->assertSame('obiora-radarr', $package->effectiveContainerName());
    }

    public function test_respects_installable_flag(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(base_path('packages/letsencrypt/manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $package = new ApplicationPackage('letsencrypt', base_path('packages/letsencrypt'), $manifest);

        $this->assertFalse($package->isInstallable());
    }

    public function test_nginx_is_not_installable(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(base_path('packages/nginx/manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $package = new ApplicationPackage('nginx', base_path('packages/nginx'), $manifest);

        $this->assertFalse($package->isInstallable());
    }
}

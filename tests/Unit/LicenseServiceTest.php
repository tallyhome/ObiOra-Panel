<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\License;
use App\Models\Setting;
use App\Services\Core\AdminLicenceClient;
use App\Services\Core\LicenseManager;
use App\Services\Core\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LicenseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_activate_stores_license_when_adminlicence_disabled(): void
    {
        $this->seed();

        config(['license.enabled' => false]);

        $service = new LicenseService(
            new AdminLicenceClient,
            new LicenseManager(new AdminLicenceClient),
        );

        $result = $service->activate('TEST-KEY-12345');

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(License::class, $result['license']);
        $this->assertDatabaseHas('licenses', [
            'license_key' => 'TEST-KEY-12345',
            'plan' => 'free',
            'status' => 'active',
        ]);
    }

    public function test_get_installation_uuid_from_settings(): void
    {
        $this->seed();

        Setting::query()->updateOrCreate(
            ['group' => 'installation', 'key' => 'uuid'],
            ['value' => ['uuid' => '550e8400-e29b-41d4-a716-446655440000'], 'is_public' => false],
        );

        $service = new LicenseService(
            new AdminLicenceClient,
            new LicenseManager(new AdminLicenceClient),
        );

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $service->getInstallationUuid());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StatusPageSetting;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MonitoringStatusPagePhase6Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_public_status_page_is_accessible_without_auth(): void
    {
        StatusPageSetting::current()->update([
            'is_enabled' => true,
            'title' => 'Test Status',
        ]);

        $this->get(route('status.index'))
            ->assertOk()
            ->assertSee('Test Status')
            ->assertSee('Serveurs');
    }

    public function test_status_page_returns_404_when_disabled(): void
    {
        StatusPageSetting::current()->update(['is_enabled' => false, 'title' => 'Hidden']);

        $this->get(route('status.index'))->assertNotFound();
    }
}

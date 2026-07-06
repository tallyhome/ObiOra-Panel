<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Applications\ApplicationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PluginTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_plugins_marketplace(): void
    {
        $this->seed();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        $this->actingAs($user)->get('/plugins')->assertOk();
    }

    public function test_catalog_discovers_packages(): void
    {
        $catalog = app(ApplicationCatalog::class);
        $slugs = $catalog->all()->pluck('slug')->all();

        $this->assertContains('netdata', $slugs);
        $this->assertContains('jellyfin', $slugs);
        $this->assertGreaterThanOrEqual(5, count($slugs));
    }
}

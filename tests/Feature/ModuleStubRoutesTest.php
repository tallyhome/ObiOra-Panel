<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\ModuleStubRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ModuleStubRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_stub_module_pages_require_auth(): void
    {
        $slug = array_key_first(ModuleStubRegistry::infrastructure());
        if ($slug === null) {
            $this->markTestSkipped('Aucun module stub — route modules.stub non enregistrée.');

            return;
        }

        $response = $this->get(route('modules.stub', $slug));

        $response->assertRedirect();
        $this->assertContains(
            $response->headers->get('Location'),
            [route('login'), route('setup')],
        );
    }

    public function test_authenticated_user_can_open_stub_modules(): void
    {
        $slugs = array_keys(ModuleStubRegistry::infrastructure());
        if ($slugs === []) {
            $this->markTestSkipped('Aucun module stub infrastructure restant.');
        }

        $this->seed();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        foreach (array_keys(ModuleStubRegistry::infrastructure()) as $slug) {
            $this->actingAs($user)
                ->get(route('modules.stub', $slug))
                ->assertOk()
                ->assertSee((string) ModuleStubRegistry::get($slug)['name']);
        }
    }
}

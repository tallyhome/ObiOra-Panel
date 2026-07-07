<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\AI\AiAssistantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_page_requires_auth(): void
    {
        $this->get(route('ai.index'))->assertRedirect();
    }

    public function test_ai_page_loads_for_admin(): void
    {
        $this->seed();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        $this->actingAs($user)
            ->get(route('ai.index'))
            ->assertOk()
            ->assertSee('Assistant IA ObiOra');
    }

    public function test_local_fallback_without_api_key(): void
    {
        config([
            'obiora.ai.enabled' => true,
            'obiora.ai.api_key' => '',
            'license.enabled' => false,
        ]);

        $result = $this->app->make(AiAssistantManager::class)->chat('Comment améliorer mon score ?');

        $this->assertTrue($result['offline']);
        $this->assertStringContainsString('Mode local', $result['content']);
    }
}

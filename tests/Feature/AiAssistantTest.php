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

    public function test_deepseek_insufficient_balance_shows_recharge_hint(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.deepseek.com/*' => \Illuminate\Support\Facades\Http::response([
                'error' => [
                    'message' => 'Insufficient Balance',
                    'type' => 'unknown_error',
                    'code' => 'invalid_request_error',
                ],
            ], 402),
        ]);

        config([
            'obiora.ai.enabled' => true,
            'obiora.ai.api_key' => 'sk-test-key',
            'obiora.ai.provider' => 'deepseek',
            'license.enabled' => false,
        ]);

        $result = $this->app->make(AiAssistantManager::class)->chat('Que dois-je vérifier concernant : Ouvrir le monitoring ?');

        $this->assertTrue($result['offline']);
        $this->assertStringContainsString('solde API insuffisant', $result['content']);
        $this->assertStringNotContainsString('sans clé API cloud', $result['content']);
        $this->assertStringContainsString('alerte(s) non lue(s)', $result['content']);
    }
}

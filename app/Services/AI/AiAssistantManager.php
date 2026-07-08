<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\License;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class AiAssistantManager
{
    public function __construct(
        private readonly AiContextBuilder $context,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('obiora.ai.enabled', true);
    }

    public function isAllowedForCurrentPlan(): bool
    {
        if (! config('license.enabled', false)) {
            return true;
        }

        $plan = License::query()->latest('id')->value('plan') ?? 'free';

        return in_array($plan, ['pro', 'enterprise'], true);
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{content: string, provider: string, offline: bool}
     */
    public function chat(string $userMessage, array $history = []): array
    {
        if (! $this->isEnabled()) {
            return $this->offlineReply('Assistant IA désactivé (OBIORA_AI_ENABLED=false).');
        }

        if (! $this->isAllowedForCurrentPlan()) {
            return $this->offlineReply('Assistant IA réservé aux plans Pro et Enterprise.');
        }

        $apiKey = (string) config('obiora.ai.api_key', '');
        if ($apiKey === '') {
            return $this->localFallback($userMessage);
        }

        $ctx = $this->context->build();
        $messages = [
            ['role' => 'system', 'content' => $this->context->systemPrompt($ctx)],
        ];

        foreach (array_slice($history, -10) as $item) {
            if (isset($item['role'], $item['content'])) {
                $messages[] = ['role' => $item['role'], 'content' => (string) $item['content']];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $content = $this->callProvider($messages);

            return [
                'content' => $content,
                'provider' => (string) config('obiora.ai.provider', 'openai'),
                'offline' => false,
            ];
        } catch (\Throwable $e) {
            Log::warning('AI assistant provider error', ['message' => $e->getMessage()]);

            $fallback = $this->localFallback($userMessage);
            $fallback['content'] = "Provider IA indisponible ({$e->getMessage()}).\n\n".$fallback['content'];

            return $fallback;
        }
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function callProvider(array $messages): string
    {
        $provider = (string) config('obiora.ai.provider', 'openai');

        return match ($provider) {
            'anthropic' => $this->callAnthropic($messages),
            'ollama' => $this->callOpenAiCompatible(
                $messages,
                (string) (config('obiora.ai.base_url') ?: 'http://127.0.0.1:11434/v1'),
            ),
            'deepseek' => $this->callOpenAiCompatible(
                $messages,
                (string) (config('obiora.ai.base_url') ?: 'https://api.deepseek.com/v1'),
            ),
            'moonshot', 'kimi' => $this->callOpenAiCompatible(
                $messages,
                (string) (config('obiora.ai.base_url') ?: 'https://api.moonshot.cn/v1'),
            ),
            default => $this->callOpenAiCompatible(
                $messages,
                (string) (config('obiora.ai.base_url') ?: 'https://api.openai.com/v1'),
            ),
        };
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function callOpenAiCompatible(array $messages, string $baseUrl): string
    {
        $response = Http::withToken((string) config('obiora.ai.api_key'))
            ->timeout(60)
            ->post(rtrim($baseUrl, '/').'/chat/completions', [
                'model' => config('obiora.ai.model', 'gpt-4o-mini'),
                'messages' => $messages,
                'max_tokens' => (int) config('obiora.ai.max_tokens', 2048),
                'temperature' => 0.4,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().': '.$response->body());
        }

        return (string) ($response->json('choices.0.message.content') ?? '');
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function callAnthropic(array $messages): string
    {
        $system = '';
        $filtered = [];
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system = $message['content'];

                continue;
            }
            $filtered[] = $message;
        }

        $response = Http::withHeaders([
            'x-api-key' => (string) config('obiora.ai.api_key'),
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('obiora.ai.model', 'claude-3-5-haiku-latest'),
            'max_tokens' => (int) config('obiora.ai.max_tokens', 2048),
            'system' => $system,
            'messages' => $filtered,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().': '.$response->body());
        }

        $blocks = $response->json('content') ?? [];

        return collect($blocks)->where('type', 'text')->pluck('text')->implode("\n");
    }

    /**
     * @return array{content: string, provider: string, offline: bool}
     */
    private function localFallback(string $userMessage): array
    {
        $ctx = $this->context->build();
        $hints = $this->context->suggestedActions($ctx);
        $links = collect($hints)->map(fn ($h) => '• '.$h['label'].' → '.$h['route'])->implode("\n");

        $doctor = $ctx['doctor']
            ? "Score Doctor : {$ctx['doctor']['score']}% ({$ctx['doctor']['critical_count']} alerte(s) critique(s))."
            : 'Aucun rapport Doctor — installez l\'agent depuis Doctor & Suite ou Monitoring.';

        $content = <<<TEXT
Mode local (sans clé API cloud).

{$doctor}
Alertes non lues : {$ctx['alerts_unread']}.

Raccourcis utiles :
{$links}

Pour votre question « {$userMessage} » :
1. Vérifiez le monitoring et les services systemd concernés.
2. Consultez les logs dans Licence & MAJ ou Services.
3. Ajoutez OBIORA_AI_API_KEY + provider deepseek/ollama/openai pour des réponses générées par un modèle.
TEXT;

        return [
            'content' => trim($content),
            'provider' => 'local',
            'offline' => true,
        ];
    }

    /**
     * @return array{content: string, provider: string, offline: bool}
     */
    private function offlineReply(string $message): array
    {
        return [
            'content' => $message,
            'provider' => 'none',
            'offline' => true,
        ];
    }
}

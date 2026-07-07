<?php

declare(strict_types=1);

namespace Modules\AI\Livewire;

use App\Services\AI\AiAssistantManager;
use App\Services\AI\AiContextBuilder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Assistant IA')]
final class AiAssistant extends Component
{
    public string $prompt = '';

    /** @var list<array{role: string, content: string, offline?: bool}> */
    public array $messages = [];

    public bool $thinking = false;

    /** @var list<array{label: string, route: string}> */
    public array $suggestions = [];

    public bool $enabled = false;

    public bool $planAllowed = true;

    public function mount(AiAssistantManager $ai, AiContextBuilder $context): void
    {
        $this->enabled = $ai->isEnabled();
        $this->planAllowed = $ai->isAllowedForCurrentPlan();
        $this->suggestions = $context->suggestedActions($context->build());

        if ($this->messages === []) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Bonjour — je suis l\'assistant ObiOra. Posez une question sur votre serveur, le monitoring Doctor ou la marketplace.',
                'offline' => false,
            ];
        }
    }

    public function send(AiAssistantManager $ai): void
    {
        $text = trim($this->prompt);
        if ($text === '' || $this->thinking) {
            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $text];
        $this->prompt = '';
        $this->thinking = true;

        $history = array_map(
            fn (array $m) => ['role' => $m['role'], 'content' => $m['content']],
            array_filter($this->messages, fn (array $m) => $m['role'] !== 'assistant' || ! str_contains($m['content'], 'Bonjour — je suis')),
        );

        $result = $ai->chat($text, $history);

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $result['content'] !== '' ? $result['content'] : 'Réponse vide du provider.',
            'offline' => $result['offline'],
        ];

        $this->thinking = false;
    }

    public function clearChat(): void
    {
        $this->messages = [[
            'role' => 'assistant',
            'content' => 'Conversation effacée. Comment puis-je vous aider ?',
            'offline' => false,
        ]];
    }

    public function useSuggestion(int $index): void
    {
        $action = $this->suggestions[$index] ?? null;
        if ($action === null) {
            return;
        }
        $this->prompt = 'Que dois-je vérifier concernant : '.$action['label'].' ?';
    }

    public function render()
    {
        return view('ai::livewire.ai-assistant');
    }
}

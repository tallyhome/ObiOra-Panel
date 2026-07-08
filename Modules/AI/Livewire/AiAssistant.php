<?php

declare(strict_types=1);

namespace Modules\AI\Livewire;

use App\Services\AI\AiActionExecutor;
use App\Services\AI\AiAssistantManager;
use App\Services\AI\AiContextBuilder;
use App\Services\AI\AiConversationStore;
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

    public bool $hasApiKey = false;

    public string $providerLabel = 'local';

    public ?int $conversationId = null;

    public function mount(
        AiAssistantManager $ai,
        AiContextBuilder $context,
        AiConversationStore $store,
    ): void {
        $this->enabled = $ai->isEnabled();
        $this->planAllowed = $ai->isAllowedForCurrentPlan();
        $this->hasApiKey = (string) config('obiora.ai.api_key', '') !== '';
        $this->providerLabel = match ((string) config('obiora.ai.provider', 'openai')) {
            'deepseek' => 'DeepSeek',
            'ollama' => 'Ollama',
            'anthropic' => 'Anthropic',
            'moonshot', 'kimi' => 'Kimi / Moonshot',
            default => 'OpenAI',
        };
        $this->suggestions = $context->suggestedActions($context->build());

        $conversation = $store->loadOrCreate($this->conversationId);
        $this->conversationId = $conversation->id;

        $stored = $store->messagesForUi($conversation);
        if ($stored !== []) {
            $this->messages = $stored;

            return;
        }

        $welcome = 'Bonjour — je suis l\'assistant ObiOra. Posez une question sur votre serveur, le monitoring Doctor ou la marketplace.';
        $this->messages[] = ['role' => 'assistant', 'content' => $welcome, 'offline' => false];
        $store->append($conversation, 'assistant', $welcome);
    }

    public function send(
        AiAssistantManager $ai,
        AiConversationStore $store,
        AiActionExecutor $actions,
    ): void {
        $text = trim($this->prompt);
        if ($text === '' || $this->thinking || $this->conversationId === null) {
            return;
        }

        $conversation = $store->loadOrCreate($this->conversationId);
        $this->messages[] = ['role' => 'user', 'content' => $text];
        $store->append($conversation, 'user', $text);
        $this->prompt = '';
        $this->thinking = true;

        $actionResult = $actions->tryExecute($text);
        if ($actionResult['handled']) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $actionResult['message'],
                'offline' => false,
            ];
            $store->append($conversation, 'assistant', $actionResult['message']);
            $this->thinking = false;

            return;
        }

        $history = array_map(
            fn (array $m) => ['role' => $m['role'], 'content' => $m['content']],
            array_filter($this->messages, fn (array $m) => $m['role'] === 'user' || $m['role'] === 'assistant'),
        );

        $result = $ai->chat($text, $history);
        $content = $result['content'] !== '' ? $result['content'] : 'Réponse vide du provider.';

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $content,
            'offline' => $result['offline'],
        ];
        $store->append($conversation, 'assistant', $content, $result['offline']);

        $this->thinking = false;
    }

    public function clearChat(AiConversationStore $store): void
    {
        if ($this->conversationId === null) {
            return;
        }

        $conversation = $store->loadOrCreate($this->conversationId);
        $store->clear($conversation);

        $msg = 'Conversation effacée. Comment puis-je vous aider ?';
        $this->messages = [['role' => 'assistant', 'content' => $msg, 'offline' => false]];
        $store->append($conversation, 'assistant', $msg);
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

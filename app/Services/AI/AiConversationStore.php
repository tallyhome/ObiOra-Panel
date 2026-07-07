<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Core\ServerManager;

final class AiConversationStore
{
    public function __construct(
        private readonly ServerManager $servers,
    ) {}

    public function loadOrCreate(?int $conversationId): AiConversation
    {
        $userId = auth()->id();
        if ($userId === null) {
            throw new \RuntimeException('Utilisateur non authentifié.');
        }

        if ($conversationId !== null) {
            $existing = AiConversation::query()
                ->where('user_id', $userId)
                ->find($conversationId);

            if ($existing !== null) {
                return $existing;
            }
        }

        return AiConversation::query()->create([
            'user_id' => $userId,
            'server_id' => $this->servers->getCurrentServer()?->id,
            'title' => 'Conversation '.now()->format('d/m H:i'),
        ]);
    }

    /**
     * @return list<array{role: string, content: string, offline?: bool}>
     */
    public function messagesForUi(AiConversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('id')
            ->get()
            ->map(fn (AiMessage $m) => [
                'role' => $m->role,
                'content' => $m->content,
                'offline' => $m->offline,
            ])
            ->all();
    }

    public function append(AiConversation $conversation, string $role, string $content, bool $offline = false): void
    {
        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'offline' => $offline,
        ]);
    }

    public function clear(AiConversation $conversation): void
    {
        $conversation->messages()->delete();
    }
}

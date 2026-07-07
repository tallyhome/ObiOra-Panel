<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ServiceStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $serverId,
        public readonly string $service,
        public readonly string $action,
        public readonly bool $success,
        public readonly string $output,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('obiora.server.'.$this->serverId);
    }

    public function broadcastAs(): string
    {
        return 'service.state';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->serverId,
            'service' => $this->service,
            'action' => $this->action,
            'success' => $this->success,
            'output' => $this->output,
            'at' => now()->toIso8601String(),
        ];
    }
}

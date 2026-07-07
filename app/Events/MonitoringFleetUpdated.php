<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MonitoringFleetUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $servers
     * @param  list<array<string, mixed>>  $alerts
     */
    public function __construct(
        public readonly array $servers,
        public readonly array $alerts,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('obiora.monitoring');
    }

    public function broadcastAs(): string
    {
        return 'monitoring.fleet';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'servers' => $this->servers,
            'alerts' => $this->alerts,
            'at' => now()->toIso8601String(),
        ];
    }
}

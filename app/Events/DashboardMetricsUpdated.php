<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DashboardMetricsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $metrics
     * @param  list<array<string, mixed>>  $services
     */
    public function __construct(
        public readonly int $serverId,
        public readonly array $metrics,
        public readonly array $services,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('obiora.server.'.$this->serverId);
    }

    public function broadcastAs(): string
    {
        return 'dashboard.metrics';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->serverId,
            'metrics' => $this->metrics,
            'services' => $this->services,
            'at' => now()->toIso8601String(),
        ];
    }
}

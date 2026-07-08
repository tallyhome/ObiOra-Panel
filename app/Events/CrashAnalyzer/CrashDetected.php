<?php

declare(strict_types=1);

namespace App\Events\CrashAnalyzer;

use App\Models\CrashAnalyzerEvent;
use App\Models\Server;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CrashDetected implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Server $server,
        public readonly CrashAnalyzerEvent $event,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('obiora.crash-analyzer')];
    }

    public function broadcastAs(): string
    {
        return 'crash.detected';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'event_type' => $this->event->event_type,
            'severity' => $this->event->severity,
            'title' => $this->event->title,
            'detected_at' => $this->event->detected_at?->toIso8601String(),
        ];
    }
}

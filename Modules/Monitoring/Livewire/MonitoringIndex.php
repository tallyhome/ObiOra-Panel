<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Models\Server;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring Obiora')]
final class MonitoringIndex extends Component
{
    /** @var list<array<string, mixed>> */
    public array $servers = [];

    public string $panelUrl;

    public function mount(): void
    {
        $this->panelUrl = rtrim((string) config('app.url'), '/');
        $this->loadServers();
    }

    public function loadServers(): void
    {
        $this->servers = Server::query()
            ->with('latestDiagnosticReport')
            ->orderBy('name')
            ->get()
            ->map(fn (Server $server) => [
                'id' => $server->id,
                'name' => $server->name,
                'ip' => $server->ip_address,
                'status' => $server->status->value,
                'last_seen' => $server->last_seen_at?->diffForHumans(),
                'score' => $server->latestDiagnosticReport?->score,
                'doctor_status' => $server->latestDiagnosticReport?->status,
                'critical' => count($server->latestDiagnosticReport?->critical_findings ?? []),
                'report_at' => $server->latestDiagnosticReport?->generated_at?->format('d/m/Y H:i'),
                'agent_token' => $server->agent_token,
            ])
            ->all();
    }

    public function render()
    {
        return view('monitoring::livewire.monitoring-index');
    }
}

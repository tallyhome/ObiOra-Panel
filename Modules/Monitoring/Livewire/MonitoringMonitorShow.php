<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Services\Monitoring\MonitorRunnerService;
use App\Support\UserTimezone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Métriques moniteur')]
final class MonitoringMonitorShow extends Component
{
    public Monitor $monitor;

    public function mount(Monitor $monitor): void
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);
        $this->monitor = $monitor;
    }

    public function render(MonitorRunnerService $runner)
    {
        $stats = $runner->statsForMonitor($this->monitor);

        $recentChecks = MonitorCheck::query()
            ->where('monitor_id', $this->monitor->id)
            ->orderByDesc('checked_at')
            ->limit(50)
            ->get()
            ->map(fn (MonitorCheck $check) => [
                'status' => $check->status,
                'response_ms' => $check->response_ms,
                'checked_at' => UserTimezone::format($check->checked_at, 'd/m/Y H:i:s'),
                'error' => $check->metrics['error'] ?? ($check->status === 'down' ? ($check->metrics['http_code'] ?? '—') : null),
                'http_code' => $check->metrics['http_code'] ?? null,
            ]);

        return view('monitoring::livewire.monitoring-monitor-show', [
            'stats' => $stats,
            'chartSeries' => $runner->chartSeriesForMonitor($this->monitor),
            'recentChecks' => $recentChecks,
            'timezoneFooter' => UserTimezone::label(),
            'nowLabel' => UserTimezone::now()->format('d/m/Y H:i:s'),
        ]);
    }
}

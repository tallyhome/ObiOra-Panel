<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Services\Monitoring\MonitorRunnerService;
use App\Services\Monitoring\MonitorVisitService;
use App\Support\UserTimezone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Métriques moniteur')]
final class MonitoringMonitorShow extends Component
{
    public Monitor $monitor;

    public string $timePreset = '24h';

    public function mount(Monitor $monitor): void
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);
        $this->monitor = $monitor;
    }

    public function setPreset(string $preset): void
    {
        $this->timePreset = $preset;
    }

    public function render(MonitorRunnerService $runner, MonitorVisitService $visits)
    {
        $range = $runner->resolvePreset($this->timePreset);
        $range24h = $runner->resolvePreset('24h');
        $stats = $runner->statsForPeriod($this->monitor, $range['from'], $range['to']);
        $stats24h = $runner->statsForPeriod($this->monitor, $range24h['from'], $range24h['to']);
        $visitStats = $visits->statsForMonitor($this->monitor);
        $visits->ensureTrackToken($this->monitor->refresh());

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
            'stats24h' => $stats24h,
            'visitStats' => $visitStats,
            'embedSnippet' => $visits->embedSnippet($this->monitor),
            'chartSeries' => $runner->chartSeriesForPeriod($this->monitor, $range['from'], $range['to']),
            'statusTimeline' => $runner->statusTimelineForPeriod($this->monitor, $range['from'], $range['to'], $range['preset'] ?? $this->timePreset),
            'recentChecks' => $recentChecks,
            'timePreset' => $this->timePreset,
            'presets' => ['1h', '6h', '24h', '3d', '7d', '30d', '3M', '6M', '1Y'],
            'rangeLabel' => $range['label'],
            'timezoneFooter' => UserTimezone::label(),
            'nowLabel' => UserTimezone::now()->format('d/m/Y H:i:s'),
        ]);
    }
}

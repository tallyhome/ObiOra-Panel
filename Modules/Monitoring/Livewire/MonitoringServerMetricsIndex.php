<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Models\Server;
use App\Services\Monitoring\ServerMetricsService;
use App\Support\UserTimezone;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Métriques serveur')]
final class MonitoringServerMetricsIndex extends Component
{
    public Server $server;

    public string $timePreset = '24h';

    public string $activeTab = 'overview';

    public function mount(Server $server): void
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);
        $this->server = $server;
    }

    public function setPreset(string $preset): void
    {
        $this->timePreset = $preset;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render(ServerMetricsService $metrics)
    {
        $range = $metrics->resolvePreset($this->timePreset);
        $dashboard = $metrics->dashboard($this->server, $range['from'], $range['to'], $range['resolution']);

        return view('monitoring::livewire.monitoring-server-metrics-index', [
            'dashboard' => $dashboard,
            'timePreset' => $this->timePreset,
            'activeTab' => $this->activeTab,
            'presets' => ['1h', '6h', '24h', '3d', '7d', '30d', '3M', '6M', '1Y'],
            'timezoneFooter' => UserTimezone::label(),
            'chartPayload' => json_encode($dashboard['series'], JSON_THROW_ON_ERROR),
        ]);
    }
}

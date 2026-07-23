<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Models\Server;
use App\Services\Monitoring\ServerMetricsService;
use App\Support\UserTimezone;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Métriques serveur')]
final class MonitoringServerMetricsIndex extends Component
{
    public Server $server;

    #[Url]
    public string $timePreset = '24h';

    #[Url]
    public string $activeTab = 'overview';

    public function mount(Server $server): void
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);
        $this->server = $server;
        $this->setPreset($this->timePreset);
        $this->setTab($this->activeTab);
    }

    public function setPreset(string $preset): void
    {
        $allowed = ['1h', '6h', '24h', '3d', '7d', '30d', '3m', '6m', '1y'];
        $normalized = strtolower($preset);
        $this->timePreset = in_array($normalized, $allowed, true) ? $normalized : '24h';
    }

    public function setTab(string $tab): void
    {
        $allowed = ['overview', 'cpu', 'memory', 'disk', 'network', 'processes'];
        $this->activeTab = in_array($tab, $allowed, true) ? $tab : 'overview';
    }

    public function render(ServerMetricsService $metrics)
    {
        $range = $metrics->resolvePreset($this->timePreset);
        $dashboard = $metrics->dashboard($this->server, $range['from'], $range['to'], $range['resolution']);

        return view('monitoring::livewire.monitoring-server-metrics-index', [
            'dashboard' => $dashboard,
            'timePreset' => $this->timePreset,
            'activeTab' => $this->activeTab,
            'presets' => ['1h', '6h', '24h', '3d', '7d', '30d', '3m', '6m', '1y'],
            'timezoneFooter' => UserTimezone::label(),
            'chartPayload' => [
                'series' => $dashboard['series'],
                'network' => $dashboard['network_series'],
            ],
        ]);
    }
}

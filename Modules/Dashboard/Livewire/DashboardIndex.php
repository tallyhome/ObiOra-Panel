<?php

declare(strict_types=1);

namespace Modules\Dashboard\Livewire;

use App\Services\Core\ServerManager;
use App\Services\System\MetricsCollector;
use App\Services\System\ServiceManager;
use App\Support\DashboardHealth;
use App\Support\NetworkMetrics;
use App\Support\Realtime;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
final class DashboardIndex extends Component
{
    /** @var array<string, mixed> */
    public array $metrics = [];

    /** @var list<array{name: string, load: string, active: string, sub: string, description: string}> */
    public array $services = [];

    public string $serverName = '';

    /** @var array<string, mixed> */
    public array $glance = [];

    /** @var array<string, mixed> */
    public array $network = [];

  /** Intervalle de rafraîchissement auto (secondes). 0 = désactivé. */
    public int $pollInterval = 10;

    public bool $realtimeEnabled = false;

    public function mount(
        MetricsCollector $collector,
        ServerManager $serverManager,
        ServiceManager $serviceManager,
    ): void {
        $this->realtimeEnabled = Realtime::enabled();
        $defaultPoll = (int) session('dashboard_poll_interval', $this->realtimeEnabled
            ? (int) config('obiora.realtime.fallback_poll_seconds', 10)
            : 10);
        $this->pollInterval = $defaultPoll;
        if ($this->realtimeEnabled && $this->pollInterval === 0) {
            $this->pollInterval = (int) config('obiora.realtime.fallback_poll_seconds', 10);
        }
        session(['dashboard_poll_interval' => $this->pollInterval]);
        $this->loadData($collector, $serverManager, $serviceManager);
    }

    public function updatedPollInterval($value): void
    {
        $allowed = [0, 3, 5, 10, 30, 60];
        $value = (int) $value;
        $this->pollInterval = in_array($value, $allowed, true) ? $value : 10;
        session(['dashboard_poll_interval' => $this->pollInterval]);
        $this->dispatch('dashboard-poll-changed', interval: $this->pollInterval);
    }

    #[On('server-changed')]
    public function refreshMetrics(
        MetricsCollector $collector,
        ServerManager $serverManager,
        ServiceManager $serviceManager,
    ): void {
        $this->loadData($collector, $serverManager, $serviceManager);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  list<array<string, mixed>>  $services
     */
    #[On('realtime-dashboard-metrics')]
    public function onRealtimeMetrics(array $metrics = [], array $services = []): void
    {
        if ($metrics !== []) {
            $this->metrics = $metrics;
            $this->glance = DashboardHealth::glance($this->metrics);
            $this->network = $this->metrics['network'] ?? [];
            $this->dispatch('dashboard-refreshed', metrics: $this->metrics);
        }

        if ($services !== []) {
            $this->services = $this->filterDashboardServices($services);
        }
    }

    #[On('realtime-service-state')]
    public function onRealtimeServiceState(
        MetricsCollector $collector,
        ServerManager $serverManager,
        ServiceManager $serviceManager,
    ): void {
        $this->loadData($collector, $serverManager, $serviceManager);
    }

    public function refresh(
        MetricsCollector $collector,
        ServerManager $serverManager,
        ServiceManager $serviceManager,
    ): void {
        $this->loadData($collector, $serverManager, $serviceManager);
    }

    /**
     * @return array<string, mixed>
     */
    public function networkRates(NetworkMetrics $networkMetrics, ServerManager $serverManager): array
    {
        $this->network = $networkMetrics->snapshot($serverManager->getCurrentServer());

        return $this->network;
    }

    private function loadData(
        MetricsCollector $collector,
        ServerManager $serverManager,
        ServiceManager $serviceManager,
    ): void {
        $server = $serverManager->getCurrentServer();
        $this->serverName = $server?->name ?? 'Aucun serveur';
        $this->metrics = $collector->collect($server);
        $this->glance = DashboardHealth::glance($this->metrics);
        $this->network = $this->metrics['network'] ?? [];
        $this->services = $this->filterDashboardServices($serviceManager->list($server));
        $this->dispatch('dashboard-refreshed', metrics: $this->metrics);
    }

    /**
     * @param  list<array{name: string, load: string, active: string, sub: string, description: string}>  $services
     * @return list<array{name: string, load: string, active: string, sub: string, description: string}>
     */
    private function filterDashboardServices(array $services): array
    {
        $keywords = [
            'nginx', 'php-fpm', 'mariadb', 'mysqld', 'mysql', 'redis',
            'obiora', 'docker', 'fail2ban', 'supervisord', 'httpd',
        ];

        $filtered = array_values(array_filter($services, function (array $svc) use ($keywords): bool {
            $name = strtolower($svc['name']);

            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    return true;
                }
            }

            return false;
        }));

        usort($filtered, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return array_slice($filtered, 0, 12);
    }

    public function render()
    {
        return view('dashboard::livewire.dashboard-index');
    }
}

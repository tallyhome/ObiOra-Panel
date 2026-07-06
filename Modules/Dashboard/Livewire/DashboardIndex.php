<?php

declare(strict_types=1);

namespace Modules\Dashboard\Livewire;

use App\Services\Core\ServerManager;
use App\Services\System\MetricsCollector;
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

    public string $serverName = '';

    public function mount(MetricsCollector $collector, ServerManager $serverManager): void
    {
        $this->loadMetrics($collector, $serverManager);
    }

    #[On('server-changed')]
    public function refreshMetrics(MetricsCollector $collector, ServerManager $serverManager): void
    {
        $this->loadMetrics($collector, $serverManager);
    }

    public function refresh(MetricsCollector $collector, ServerManager $serverManager): void
    {
        $this->loadMetrics($collector, $serverManager);
    }

    private function loadMetrics(MetricsCollector $collector, ServerManager $serverManager): void
    {
        $server = $serverManager->getCurrentServer();
        $this->serverName = $server?->name ?? 'Aucun serveur';
        $this->metrics = $collector->collect($server);
    }

    public function render()
    {
        return view('dashboard::livewire.dashboard-index');
    }
}

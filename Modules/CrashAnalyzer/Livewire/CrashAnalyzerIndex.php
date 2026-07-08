<?php

declare(strict_types=1);

namespace Modules\CrashAnalyzer\Livewire;

use App\Models\Server;
use App\Services\CrashAnalyzer\CrashAnalyzerMetricsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Crash Analyzer')]
final class CrashAnalyzerIndex extends Component
{
    #[Url]
    public ?int $server = null;

    public int $historyMinutes = 60;

    /** @var list<string> */
    public array $severityFilter = ['critical', 'warning', 'info'];

    /** @var string */
    public string $eventTypeFilter = '';

    public function mount(): void
    {
        $this->historyMinutes = (int) config('crash_analyzer.history_minutes', 60);

        if ($this->server === null) {
            $this->server = Server::query()->orderBy('name')->value('id');
        }
    }

    public function render(CrashAnalyzerMetricsService $metrics)
    {
        $servers = Server::query()->orderBy('name')->get(['id', 'name', 'hostname', 'status']);
        $selected = $servers->firstWhere('id', $this->server);
        $dashboard = $selected
            ? $metrics->dashboardData($selected, $this->historyMinutes)
            : null;

        if ($dashboard !== null && $this->eventTypeFilter !== '') {
            $dashboard['events'] = array_values(array_filter(
                $dashboard['events'],
                fn (array $e) => str_contains($e['event_type'], $this->eventTypeFilter),
            ));
        }

        if ($dashboard !== null && $this->severityFilter !== []) {
            $dashboard['events'] = array_values(array_filter(
                $dashboard['events'],
                fn (array $e) => in_array($e['severity'], $this->severityFilter, true),
            ));
        }

        return view('crash-analyzer::livewire.crash-analyzer-index', [
            'servers' => $servers,
            'selectedServer' => $selected,
            'dashboard' => $dashboard,
            'panelUrl' => rtrim((string) config('app.url'), '/'),
            'criticalTypes' => config('crash_analyzer.critical_event_types', []),
        ]);
    }
}

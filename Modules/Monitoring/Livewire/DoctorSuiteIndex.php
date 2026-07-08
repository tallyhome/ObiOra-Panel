<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Models\DiagnosticReport;
use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Support\DoctorInstallHelper;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Doctor & Suite')]
final class DoctorSuiteIndex extends Component
{
    public ?int $serverId = null;

    public string $localInstall = '';

    public string $remoteInstall = '';

    public function mount(ServerManager $servers, DoctorInstallHelper $doctor): void
    {
        abort_unless(auth()->user()?->can('modules.view'), 403);

        $current = $servers->getCurrentServer();
        $this->serverId = $current?->id;
        $this->localInstall = $doctor->localCommand($current);
        $this->remoteInstall = $doctor->remoteCommand($current);
    }

    public function render()
    {
        $servers = Server::query()
            ->with('latestDiagnosticReport')
            ->orderByDesc('is_master')
            ->orderBy('name')
            ->get();

        $server = $this->serverId !== null
            ? $servers->firstWhere('id', $this->serverId)
            : $servers->first();

        $report = $server?->latestDiagnosticReport;

        $reportCount = DiagnosticReport::query()->count();
        $lastReportAt = DiagnosticReport::query()->max('generated_at');

        return view('monitoring::livewire.doctor-suite-index', [
            'server' => $server,
            'report' => $report,
            'doctorFleet' => $servers,
            'reportCount' => $reportCount,
            'lastReportAt' => $lastReportAt,
            'suiteUrl' => (string) config('obiora.suite.url', ''),
        ]);
    }
}

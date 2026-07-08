<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

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
    public ?Server $server = null;

    public string $localInstall = '';

    public string $remoteInstall = '';

    public function mount(ServerManager $servers, DoctorInstallHelper $doctor): void
    {
        abort_unless(auth()->user()?->can('modules.view'), 403);

        $this->server = $servers->getCurrentServer();
        $this->localInstall = $doctor->localCommand($this->server);
        $this->remoteInstall = $doctor->remoteCommand($this->server);
    }

    public function render()
    {
        $report = $this->server?->latestDiagnosticReport;

        return view('monitoring::livewire.doctor-suite-index', [
            'report' => $report,
            'suiteUrl' => (string) config('obiora.suite.url', ''),
        ]);
    }
}

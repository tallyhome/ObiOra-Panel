<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Models\Server;
use App\Services\Core\ServerManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Doctor & Suite')]
final class DoctorSuiteIndex extends Component
{
    public ?Server $server = null;

    public string $panelUrl = '';

    public string $installHint = '';

    public function mount(ServerManager $servers): void
    {
        abort_unless(auth()->user()?->can('modules.view'), 403);

        $this->server = $servers->getCurrentServer();
        $this->panelUrl = (string) config('app.url');
        $this->installHint = sprintf(
            'OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%s bash %s/agent/scripts/install-doctor-agent.sh',
            $this->panelUrl,
            $this->server?->id ?? '1',
            base_path(),
        );
    }

    public function render()
    {
        $report = $this->server?->latestDiagnosticReport;

        return view('monitoring::livewire.doctor-suite-index', [
            'report' => $report,
            'doctorEnabled' => (bool) config('obiora.diagnostics.signing_key'),
            'suiteUrl' => (string) config('obiora.suite.url', ''),
        ]);
    }
}

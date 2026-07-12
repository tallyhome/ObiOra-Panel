<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Models\Server;
use App\Services\Monitoring\MonitoringSlaService;
use App\Services\Monitoring\ServerUnifiedProfileService;
use App\Support\UserTimezone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Fiche serveur')]
final class MonitoringServerShow extends Component
{
    public Server $server;

    #[Url(as: 'tab')]
    public string $activeTab = 'overview';

    public function mount(Server $server): void
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);
        $this->server = $server;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render(ServerUnifiedProfileService $profile, MonitoringSlaService $sla)
    {
        $data = $profile->profile($this->server);

        return view('monitoring::livewire.monitoring-server-show', [
            'profile' => $data,
            'sla' => $sla->serverReport($this->server, 30),
            'timezoneFooter' => UserTimezone::label(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Servers\Livewire;

use App\Livewire\Concerns\AuthorizesPanelAccess;
use App\Models\Server;
use App\Services\Core\ServerManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Détail serveur')]
final class ServerShow extends Component
{
    use AuthorizesPanelAccess;

    public Server $server;

    public function mount(Server $server, ServerManager $serverManager): void
    {
        $this->server = $server->load(['nodes', 'latestDiagnosticReport']);
        $serverManager->ensureDoctorSigningKey($this->server);
        $this->server->refresh();
    }

    public function regenerateAgentToken(ServerManager $serverManager): void
    {
        $this->authorizePermission('servers.manage');

        try {
            $token = $serverManager->regenerateAgentToken($this->server);
            $this->server->refresh();
            $this->dispatch('notify', type: 'success', message: 'Token agent régénéré.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function ping(ServerManager $serverManager): void
    {
        $online = $serverManager->ping($this->server);
        $this->server->refresh();

        $this->dispatch('notify', type: $online ? 'success' : 'danger', message: $online
            ? 'Serveur en ligne.'
            : 'Serveur injoignable.');
    }

    public function useServer(ServerManager $serverManager): void
    {
        $serverManager->setCurrentServer($this->server);
        $this->dispatch('server-changed', serverId: $this->server->id);
        $this->dispatch('notify', type: 'success', message: "Serveur actif : {$this->server->name}");
    }

    public function render()
    {
        return view('servers::livewire.server-show');
    }
}

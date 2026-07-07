<?php

declare(strict_types=1);

namespace Modules\Servers\Livewire;

use App\Models\Server;
use App\Services\Core\ServerManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Détail serveur')]
final class ServerShow extends Component
{
    public Server $server;

    public function mount(Server $server): void
    {
        $this->server = $server->load(['nodes', 'latestDiagnosticReport']);
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

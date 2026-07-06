<?php

declare(strict_types=1);

namespace Modules\Servers\Livewire;

use App\Enums\ServerType;
use App\Models\Server;
use App\Services\Core\ServerManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Serveurs')]
final class ServerList extends Component
{
    public function ping(int $serverId, ServerManager $serverManager): void
    {
        $server = Server::query()->findOrFail($serverId);
        $online = $serverManager->ping($server);

        $this->dispatch('notify', type: $online ? 'success' : 'danger', message: $online
            ? "Serveur {$server->name} en ligne."
            : "Serveur {$server->name} injoignable.");
    }

    public function delete(int $serverId, ServerManager $serverManager): void
    {
        $server = Server::query()->findOrFail($serverId);

        try {
            $serverManager->delete($server);
            $this->dispatch('notify', type: 'success', message: 'Serveur supprimé.');
        } catch (\RuntimeException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function render(ServerManager $serverManager)
    {
        return view('servers::livewire.server-list', [
            'servers' => $serverManager->all(),
        ]);
    }
}

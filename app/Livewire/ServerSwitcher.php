<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Server;
use App\Services\Core\ServerManager;
use Livewire\Component;

final class ServerSwitcher extends Component
{
  /** @var array<int, array{id: int, name: string, status: string, is_master: bool}> */
    public array $servers = [];

    public ?int $currentId = null;

    public function mount(ServerManager $serverManager): void
    {
        $this->servers = $serverManager->all()
            ->map(fn (Server $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'status' => $s->status->value,
                'is_master' => $s->is_master,
            ])
            ->all();

        $this->currentId = $serverManager->getCurrentServer()?->id;
    }

    public function switchServer(int $serverId, ServerManager $serverManager): void
    {
        $server = Server::query()->findOrFail($serverId);
        $serverManager->setCurrentServer($server);
        $this->currentId = $serverId;

        $this->dispatch('server-changed', serverId: $serverId);
    }

    public function render()
    {
        return view('livewire.server-switcher');
    }
}

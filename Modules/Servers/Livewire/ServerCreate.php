<?php

declare(strict_types=1);

namespace Modules\Servers\Livewire;

use App\Enums\ServerType;
use App\Services\Core\ServerManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ajouter un serveur')]
final class ServerCreate extends Component
{
    public string $name = '';

    public string $ip_address = '';

    public string $hostname = '';

    public string $type = 'vps';

    public int $agent_port = 9100;

    public function save(ServerManager $serverManager): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:vps,dedicated,cluster'],
            'agent_port' => ['required', 'integer', 'min:1', 'max:65535'],
        ]);

        $server = $serverManager->createRemote([
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'hostname' => $this->hostname ?: $this->ip_address,
            'type' => ServerType::from($this->type),
            'agent_port' => $this->agent_port,
        ]);

        session()->flash('success', "Serveur « {$server->name} » ajouté. Installez l'agent avec le token affiché.");

        $this->redirect(route('servers.show', $server), navigate: true);
    }

    public function render()
    {
        return view('servers::livewire.server-create');
    }
}

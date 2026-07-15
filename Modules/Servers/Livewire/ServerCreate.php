<?php

declare(strict_types=1);

namespace Modules\Servers\Livewire;

use App\Enums\DedicatedHostProfile;
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

    public string $host_profile = 'bare_metal';

    public string $agent_token = '';

    public int $agent_port = 9100;

    public function save(ServerManager $serverManager): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:vps,dedicated,cluster'],
            'host_profile' => ['nullable', 'string', 'in:'.implode(',', array_column(DedicatedHostProfile::selectable(), 'value'))],
            'agent_token' => ['nullable', 'string', 'min:32', 'max:128'],
            'agent_port' => ['required', 'integer', 'min:1', 'max:65535'],
        ]);

        try {
            $server = $serverManager->createRemote([
                'name' => $this->name,
                'ip_address' => $this->ip_address,
                'hostname' => $this->hostname ?: $this->ip_address,
                'type' => ServerType::from($this->type),
                'host_profile' => $this->type === 'dedicated' ? $this->host_profile : null,
                'agent_token' => trim($this->agent_token),
                'agent_port' => $this->agent_port,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addError('agent_token', $e->getMessage());

            return;
        }

        $status = $server->status->value === 'online'
            ? 'agent connecté'
            : 'en attente — installez l\'agent via SSH sur la fiche serveur';

        session()->flash('success', "Serveur « {$server->name} » enregistré ({$status}). Installez l'agent seedbox via SSH sur la fiche serveur.");

        $this->redirect(route('servers.show', $server), navigate: true);
    }

    public function render()
    {
        return view('servers::livewire.server-create');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Services\Livewire;

use App\Services\Core\ServerManager;
use App\Services\System\ServiceManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Services')]
final class ServiceList extends Component
{
    /** @var list<array{name: string, load: string, active: string, sub: string, description: string}> */
    public array $services = [];

    public string $search = '';

    public ?string $logService = null;

    public string $logOutput = '';

    public string $serverName = '';

    public function mount(ServiceManager $serviceManager, ServerManager $serverManager): void
    {
        $this->loadServices($serviceManager, $serverManager);
    }

    #[On('server-changed')]
    public function onServerChanged(ServiceManager $serviceManager, ServerManager $serverManager): void
    {
        $this->loadServices($serviceManager, $serverManager);
        $this->logService = null;
        $this->logOutput = '';
    }

    public function refresh(ServiceManager $serviceManager, ServerManager $serverManager): void
    {
        $this->loadServices($serviceManager, $serverManager);
    }

    public function runAction(string $service, string $action, ServiceManager $serviceManager): void
    {
        $result = $serviceManager->action($service, $action);
        $this->dispatch('notify', type: $result['success'] ? 'success' : 'danger', message: $result['success']
            ? "Action « {$action} » effectuée sur {$service}."
            : $result['output']);
        $this->loadServices($serviceManager, app(ServerManager::class));
    }

    public function showLogs(string $service, ServiceManager $serviceManager): void
    {
        $this->logService = $service;
        $this->logOutput = $serviceManager->logs($service);
    }

    private function loadServices(ServiceManager $serviceManager, ServerManager $serverManager): void
    {
        $server = $serverManager->getCurrentServer();
        $this->serverName = $server?->name ?? 'Aucun';
        $this->services = $serviceManager->list($server);
    }

    public function render()
    {
        $filtered = collect($this->services)
            ->when($this->search, fn ($c) => $c->filter(
                fn ($s) => str_contains(strtolower($s['name']), strtolower($this->search))
                    || str_contains(strtolower($s['description']), strtolower($this->search))
            ))
            ->values()
            ->all();

        return view('services::livewire.service-list', ['filtered' => $filtered]);
    }
}

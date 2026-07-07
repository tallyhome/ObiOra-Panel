<?php

declare(strict_types=1);

namespace Modules\Docker\Livewire;

use App\Services\Core\ServerManager;
use App\Services\Docker\DockerManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Docker')]
final class DockerIndex extends Component
{
    /** @var list<array<string, string>> */
    public array $containers = [];

    /** @var list<array<string, string>> */
    public array $images = [];

    /** @var array<string, mixed> */
    public array $dockerInfo = [];

    public string $serverName = '';

    public string $search = '';

    public string $activeTab = 'containers';

    public string $run_image = '';

    public string $run_name = '';

    public string $run_ports = '';

    public ?string $logContainer = null;

    public string $logOutput = '';

    public bool $installingDocker = false;

    public int $dockerProgress = 0;

    public string $dockerProgressMessage = '';

    public function mount(DockerManager $dockerManager, ServerManager $serverManager): void
    {
        $this->loadData($dockerManager, $serverManager);
        $this->resumeDockerInstall();
    }

    #[On('server-changed')]
    public function onServerChanged(DockerManager $dockerManager, ServerManager $serverManager): void
    {
        $this->loadData($dockerManager, $serverManager);
        $this->logContainer = null;
        $this->logOutput = '';
    }

    public function refresh(DockerManager $dockerManager, ServerManager $serverManager): void
    {
        $this->loadData($dockerManager, $serverManager);
    }

    public function installDocker(DockerManager $dockerManager, ServerManager $serverManager): void
    {
        if ($this->installingDocker) {
            return;
        }

        $result = $dockerManager->installDocker($serverManager->getCurrentServer());

        if ($result['success']) {
            $this->installingDocker = true;
            $this->dockerProgress = 2;
            $this->dockerProgressMessage = 'Installation Docker démarrée…';
        }

        $this->dispatch('notify', type: $result['success'] ? 'info' : 'danger', message: $result['message']);
    }

    public function pollDockerInstall(DockerManager $dockerManager, ServerManager $serverManager): void
    {
        $status = \Illuminate\Support\Facades\Cache::get('obiora_progress:docker_install');

        if (! is_array($status)) {
            $this->installingDocker = false;

            return;
        }

        $this->dockerProgress = (int) ($status['progress'] ?? 0);
        $this->dockerProgressMessage = (string) ($status['message'] ?? '');
        $this->installingDocker = (bool) ($status['running'] ?? false);

        if (! $this->installingDocker && ($status['success'] ?? null) !== null) {
            $this->loadData($dockerManager, $serverManager);
            $this->dispatch('notify', type: ($status['success'] ?? false) ? 'success' : 'danger', message: $this->dockerProgressMessage);
        }
    }

    private function resumeDockerInstall(): void
    {
        $status = \Illuminate\Support\Facades\Cache::get('obiora_progress:docker_install');

        if (! is_array($status) || ! ($status['running'] ?? false)) {
            return;
        }

        $this->installingDocker = true;
        $this->dockerProgress = (int) ($status['progress'] ?? 0);
        $this->dockerProgressMessage = (string) ($status['message'] ?? '');
    }

    public function runContainer(DockerManager $dockerManager, ServerManager $serverManager): void
    {
        $this->validate([
            'run_image' => ['required', 'string', 'max:255'],
            'run_name' => ['nullable', 'string', 'max:128', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/'],
            'run_ports' => ['nullable', 'regex:/^[0-9]+:[0-9]+$/'],
        ]);

        try {
            $result = $dockerManager->runContainer(
                $this->run_image,
                $this->run_name !== '' ? $this->run_name : null,
                $this->run_ports !== '' ? $this->run_ports : null,
                $serverManager->getCurrentServer(),
            );
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());

            return;
        }

        if ($result['success']) {
            $this->dispatch('notify', type: 'success', message: 'Conteneur démarré.');
            $this->reset(['run_image', 'run_name', 'run_ports']);
            $this->loadData($dockerManager, $serverManager);
        } else {
            $this->dispatch('notify', type: 'danger', message: $result['output']);
        }
    }

    public function containerAction(string $container, string $action, DockerManager $dockerManager, ServerManager $serverManager): void
    {
        $result = $dockerManager->containerAction($container, $action);
        $this->dispatch('notify', type: $result['success'] ? 'success' : 'danger', message: $result['success']
            ? "Action « {$action} » effectuée."
            : $result['output']);
        $this->loadData($dockerManager, $serverManager);
    }

    public function showLogs(string $container, DockerManager $dockerManager): void
    {
        $this->logContainer = $container;
        $this->logOutput = $dockerManager->containerLogs($container);
    }

    public function removeImage(string $imageId, DockerManager $dockerManager, ServerManager $serverManager): void
    {
        $result = $dockerManager->removeImage($imageId);
        $this->dispatch('notify', type: $result['success'] ? 'success' : 'danger', message: $result['success']
            ? 'Image supprimée.'
            : $result['output']);
        $this->loadData($dockerManager, $serverManager);
    }

    private function loadData(DockerManager $dockerManager, ServerManager $serverManager): void
    {
        $server = $serverManager->getCurrentServer();
        $this->serverName = $server?->name ?? 'Aucun';
        $this->dockerInfo = $dockerManager->info($server);
        $this->containers = $dockerManager->containers($server);
        $this->images = $dockerManager->images($server);
    }

    public function render()
    {
        $filteredContainers = collect($this->containers)
            ->when($this->search, fn ($c) => $c->filter(
                fn ($row) => str_contains(strtolower($row['name']), strtolower($this->search))
                    || str_contains(strtolower($row['image']), strtolower($this->search))
            ))
            ->values()
            ->all();

        return view('docker::livewire.docker-index', [
            'filteredContainers' => $filteredContainers,
        ]);
    }
}

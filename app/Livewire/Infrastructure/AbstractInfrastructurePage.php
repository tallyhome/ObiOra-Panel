<?php

declare(strict_types=1);

namespace App\Livewire\Infrastructure;

use App\Services\Infrastructure\InfrastructureManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
abstract class AbstractInfrastructurePage extends Component
{
    /** @var array<string, mixed> */
    public array $data = [];

    public string $serverName = '';

    public ?string $error = null;

    public function mount(InfrastructureManager $infra): void
    {
        $this->authorizeView();
        $this->refreshData($infra);
    }

    #[On('server-changed')]
    public function onServerChanged(InfrastructureManager $infra): void
    {
        $this->refreshData($infra);
    }

    public function refresh(InfrastructureManager $infra): void
    {
        $this->refreshData($infra);
        $this->dispatch('notify', type: 'info', message: 'Données actualisées.');
    }

    protected function refreshData(InfrastructureManager $infra): void
    {
        $this->error = null;
        $this->data = $this->fetch($infra);
        if (isset($this->data['error'])) {
            $this->error = (string) $this->data['error'];
        }
        $this->serverName = (string) (auth()->user()?->name ?? 'Panel');
    }

    /** @return array<string, mixed> */
    abstract protected function fetch(InfrastructureManager $infra): array;

    abstract protected function pageTitle(): string;

    protected function authorizeView(): void
    {
        abort_unless(auth()->user()?->can('modules.view'), 403);
    }

    public function render()
    {
        return view('livewire.infrastructure.page', [
            'title' => $this->pageTitle(),
            'slug' => $this->infraSlug(),
        ]);
    }

    abstract protected function infraSlug(): string;
}

<?php

declare(strict_types=1);

namespace Modules\Firewall\Livewire;

use App\Livewire\Infrastructure\AbstractInfrastructurePage;
use App\Services\Infrastructure\InfrastructureManager;
use Livewire\Attributes\Title;

#[Title('Firewall')]
final class FirewallIndex extends AbstractInfrastructurePage
{
    public string $portAction = 'open';

    public string $portNumber = '10000';

    protected function fetch(InfrastructureManager $infra): array
    {
        return $infra->firewallStatus();
    }

    public function applyPort(InfrastructureManager $infra): void
    {
        abort_unless(auth()->user()?->can('modules.manage'), 403);

        $port = (int) $this->portNumber;
        $result = $infra->firewallPort($this->portAction, $port);

        $this->dispatch('notify', type: $result['success'] ? 'success' : 'danger', message: $result['message']);
        $this->refreshData($infra);
    }

    protected function pageTitle(): string
    {
        return 'Firewall';
    }

    protected function infraSlug(): string
    {
        return 'firewall';
    }
}

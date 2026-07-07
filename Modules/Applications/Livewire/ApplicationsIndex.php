<?php

declare(strict_types=1);

namespace Modules\Applications\Livewire;

use App\Livewire\Infrastructure\AbstractInfrastructurePage;
use App\Services\Infrastructure\InfrastructureManager;
use Livewire\Attributes\Title;

#[Title('Applications')]
final class ApplicationsIndex extends AbstractInfrastructurePage
{
    protected function fetch(InfrastructureManager $infra): array
    {
        return $infra->applicationsInventory();
    }

    protected function pageTitle(): string
    {
        return 'Applications';
    }

    protected function infraSlug(): string
    {
        return 'applications';
    }
}

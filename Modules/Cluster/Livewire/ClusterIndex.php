<?php

declare(strict_types=1);

namespace Modules\Cluster\Livewire;

use App\Livewire\Infrastructure\AbstractInfrastructurePage;
use App\Services\Infrastructure\InfrastructureManager;
use Livewire\Attributes\Title;

#[Title('Cluster')]
final class ClusterIndex extends AbstractInfrastructurePage
{
    protected function fetch(InfrastructureManager $infra): array
    {
        return $infra->clusterOverview();
    }

    protected function pageTitle(): string
    {
        return 'Cluster';
    }

    protected function infraSlug(): string
    {
        return 'cluster';
    }
}

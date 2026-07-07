<?php

declare(strict_types=1);

namespace Modules\Apache\Livewire;

use App\Livewire\Infrastructure\AbstractInfrastructurePage;
use App\Services\Infrastructure\InfrastructureManager;
use Livewire\Attributes\Title;

#[Title('Apache')]
final class ApacheIndex extends AbstractInfrastructurePage
{
    protected function fetch(InfrastructureManager $infra): array
    {
        return $infra->apacheStatus();
    }

    protected function pageTitle(): string
    {
        return 'Apache';
    }

    protected function infraSlug(): string
    {
        return 'apache';
    }
}

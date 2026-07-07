<?php

declare(strict_types=1);

namespace Modules\Nginx\Livewire;

use App\Livewire\Infrastructure\AbstractInfrastructurePage;
use App\Services\Infrastructure\InfrastructureManager;
use Livewire\Attributes\Title;

#[Title('Nginx')]
final class NginxIndex extends AbstractInfrastructurePage
{
    protected function fetch(InfrastructureManager $infra): array
    {
        return $infra->nginxVhosts();
    }

    protected function pageTitle(): string
    {
        return 'Nginx';
    }

    protected function infraSlug(): string
    {
        return 'nginx';
    }
}

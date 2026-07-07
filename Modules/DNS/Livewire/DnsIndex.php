<?php

declare(strict_types=1);

namespace Modules\DNS\Livewire;

use App\Livewire\Infrastructure\AbstractInfrastructurePage;
use App\Services\Infrastructure\InfrastructureManager;
use Livewire\Attributes\Title;

#[Title('DNS')]
final class DnsIndex extends AbstractInfrastructurePage
{
    protected function fetch(InfrastructureManager $infra): array
    {
        return $infra->dnsStatus();
    }

    protected function pageTitle(): string
    {
        return 'DNS';
    }

    protected function infraSlug(): string
    {
        return 'dns';
    }
}

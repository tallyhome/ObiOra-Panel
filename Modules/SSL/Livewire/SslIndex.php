<?php

declare(strict_types=1);

namespace Modules\SSL\Livewire;

use App\Livewire\Infrastructure\AbstractInfrastructurePage;
use App\Services\Infrastructure\InfrastructureManager;
use Livewire\Attributes\Title;

#[Title('SSL / TLS')]
final class SslIndex extends AbstractInfrastructurePage
{
    protected function fetch(InfrastructureManager $infra): array
    {
        return $infra->sslCertificates();
    }

    protected function pageTitle(): string
    {
        return 'SSL / TLS';
    }

    protected function infraSlug(): string
    {
        return 'ssl';
    }
}

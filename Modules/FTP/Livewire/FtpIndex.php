<?php

declare(strict_types=1);

namespace Modules\FTP\Livewire;

use App\Livewire\Infrastructure\AbstractInfrastructurePage;
use App\Services\Infrastructure\InfrastructureManager;
use Livewire\Attributes\Title;

#[Title('FTP')]
final class FtpIndex extends AbstractInfrastructurePage
{
    protected function fetch(InfrastructureManager $infra): array
    {
        return $infra->ftpStatus();
    }

    protected function pageTitle(): string
    {
        return 'FTP';
    }

    protected function infraSlug(): string
    {
        return 'ftp';
    }
}

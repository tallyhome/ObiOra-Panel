<?php

declare(strict_types=1);

namespace Modules\Redis\Livewire;

use App\Livewire\Infrastructure\AbstractInfrastructurePage;
use App\Services\Infrastructure\InfrastructureManager;
use Livewire\Attributes\Title;

#[Title('Redis')]
final class RedisIndex extends AbstractInfrastructurePage
{
    protected function fetch(InfrastructureManager $infra): array
    {
        return $infra->redisInfo();
    }

    protected function pageTitle(): string
    {
        return 'Redis';
    }

    protected function infraSlug(): string
    {
        return 'redis';
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Services\Applications\ApplicationCatalog;

final class PluginManager
{
    public function __construct(
        private readonly ApplicationCatalog $catalog,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function discover(): array
    {
        return $this->catalog->all()
            ->map(fn ($package) => $package->manifest)
            ->values()
            ->all();
    }
}

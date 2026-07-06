<?php

declare(strict_types=1);

namespace App\Contracts;

interface PluginInterface
{
    public function getSlug(): string;

    public function getManifest(): array;

    public function register(): void;

    public function boot(): void;
}

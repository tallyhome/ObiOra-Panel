<?php

declare(strict_types=1);

namespace App\Contracts;

interface ModuleInterface
{
    public function getSlug(): string;

    public function getName(): string;

    public function getVersion(): string;

    public function getDependencies(): array;

    public function boot(): void;

    public function register(): void;
}

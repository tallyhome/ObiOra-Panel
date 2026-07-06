<?php

declare(strict_types=1);

namespace App\Contracts;

interface ApplicationPackageInterface
{
    public function getSlug(): string;

    public function getManifest(): array;

    public function install(array $options = []): bool;

    public function uninstall(array $options = []): bool;

    public function update(array $options = []): bool;
}

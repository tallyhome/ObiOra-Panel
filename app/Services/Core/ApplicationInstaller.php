<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\ApplicationPackageInterface;
use App\Contracts\SystemExecutorInterface;
use App\Support\ManifestParser;

final class ApplicationInstaller implements ApplicationPackageInterface
{
    public function __construct(
        private readonly ManifestParser $manifestParser,
        private readonly SystemExecutorInterface $executor,
        private readonly string $slug = '',
    ) {}

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getManifest(): array
    {
        return $this->manifestParser->parseApplication($this->packagePath());
    }

    public function install(array $options = []): bool
    {
        $manifest = $this->getManifest();
        $script = $manifest['scripts']['install'] ?? 'install.sh';

        return $this->executor
            ->runScript($this->packagePath().DIRECTORY_SEPARATOR.$script, $options)
            ->successful;
    }

    public function uninstall(array $options = []): bool
    {
        $manifest = $this->getManifest();
        $script = $manifest['scripts']['uninstall'] ?? 'uninstall.sh';

        return $this->executor
            ->runScript($this->packagePath().DIRECTORY_SEPARATOR.$script, $options)
            ->successful;
    }

    public function update(array $options = []): bool
    {
        $manifest = $this->getManifest();
        $script = $manifest['scripts']['update'] ?? 'update.sh';

        return $this->executor
            ->runScript($this->packagePath().DIRECTORY_SEPARATOR.$script, $options)
            ->successful;
    }

    private function packagePath(): string
    {
        return (string) config('applications.path').DIRECTORY_SEPARATOR.$this->slug;
    }
}

<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ApplicationPackage
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function __construct(
        public string $slug,
        public string $path,
        public array $manifest,
    ) {}

    public function name(): string
    {
        return (string) ($this->manifest['name'] ?? $this->slug);
    }

    public function description(): string
    {
        return (string) ($this->manifest['description'] ?? '');
    }

    public function version(): string
    {
        return (string) ($this->manifest['version'] ?? '1.0.0');
    }

    public function category(): string
    {
        return (string) ($this->manifest['category'] ?? 'dev');
    }

    public function installScript(): string
    {
        $script = $this->manifest['scripts']['install'] ?? 'install.sh';

        return $this->path.DIRECTORY_SEPARATOR.$script;
    }

    public function uninstallScript(): string
    {
        $script = $this->manifest['scripts']['uninstall'] ?? 'uninstall.sh';

        return $this->path.DIRECTORY_SEPARATOR.$script;
    }
}

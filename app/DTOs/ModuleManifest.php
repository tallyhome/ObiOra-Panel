<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ModuleManifest
{
    /**
     * @param  array<string>  $dependencies
     * @param  array<string>  $permissions
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $version,
        public string $description,
        public array $dependencies = [],
        public array $permissions = [],
        public bool $defaultEnabled = false,
        public bool $hasRoutes = true,
        public bool $hasMigrations = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            version: (string) ($data['version'] ?? '1.0.0'),
            description: (string) ($data['description'] ?? ''),
            dependencies: (array) ($data['dependencies'] ?? []),
            permissions: (array) ($data['permissions'] ?? []),
            defaultEnabled: (bool) ($data['default_enabled'] ?? false),
            hasRoutes: (bool) ($data['routes'] ?? true),
            hasMigrations: (bool) ($data['migrations'] ?? true),
        );
    }
}

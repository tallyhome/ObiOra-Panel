<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Support\ApplicationIcon;

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

    public function systemdService(): ?string
    {
        $service = $this->manifest['runtime']['service'] ?? null;

        return is_string($service) && $service !== '' ? $service : null;
    }

    public function runtimeType(): string
    {
        return (string) ($this->manifest['runtime']['type'] ?? 'docker');
    }

    public function hasExplicitRuntime(): bool
    {
        return isset($this->manifest['runtime']['type']);
    }

    public function isInstallable(): bool
    {
        return ($this->manifest['installable'] ?? true) !== false;
    }

    public function installNotice(): string
    {
        return (string) ($this->manifest['install_notice'] ?? $this->usageNotes());
    }

    public function effectiveRuntimeType(): string
    {
        if ($this->hasExplicitRuntime()) {
            return $this->runtimeType();
        }

        $script = @file_get_contents($this->installScript());

        if (! is_string($script)) {
            return 'docker';
        }

        if (str_contains($script, 'obiora_docker_install') || preg_match('/\bdocker\s+run\b/', $script)) {
            return 'docker';
        }

        if (preg_match('/\bsystemctl\s+(?:enable|start|restart)\b/', $script)) {
            return 'systemd';
        }

        return 'binary';
    }

    public function effectiveSystemdService(): ?string
    {
        $explicit = $this->systemdService();

        if ($explicit !== null) {
            return $explicit;
        }

        if ($this->effectiveRuntimeType() !== 'systemd') {
            return null;
        }

        $script = @file_get_contents($this->installScript());

        if (is_string($script) && preg_match('/\bsystemctl\s+(?:enable|start|restart)\s+([a-zA-Z0-9@._-]+)/', $script, $matches)) {
            return $matches[1];
        }

        return $this->slug;
    }

    public function containerName(): string
    {
        return (string) ($this->manifest['runtime']['container'] ?? 'obiora-'.$this->slug);
    }

    public function effectiveContainerName(): string
    {
        if (isset($this->manifest['runtime']['container'])) {
            return $this->containerName();
        }

        return 'obiora-'.$this->slug;
    }

    public function port(): ?int
    {
        if (! isset($this->manifest['runtime']['port'])) {
            return null;
        }

        return (int) $this->manifest['runtime']['port'];
    }

    public function usageNotes(): string
    {
        return (string) ($this->manifest['runtime']['usage']
            ?? $this->manifest['runtime']['credentials']
            ?? '');
    }

    public function accessUrl(?string $host = null): ?string
    {
        $port = $this->port();

        if ($port === null) {
            return null;
        }

        $host = $host ?? 'localhost';
        $template = (string) ($this->manifest['runtime']['url'] ?? 'http://{host}:{port}');

        return str_replace(['{host}', '{port}'], [$host, (string) $port], $template);
    }

    public function uninstallScript(): string
    {
        $script = $this->manifest['scripts']['uninstall'] ?? 'uninstall.sh';

        return $this->path.DIRECTORY_SEPARATOR.$script;
    }

    /**
     * Champs du formulaire affiché avant installation (manifest.install.options).
     *
     * @return list<array{name: string, label: string, type: string, default?: string, required?: bool, min?: int, help?: string, confirm?: string}>
     */
    public function installOptions(): array
    {
        $options = $this->manifest['install']['options'] ?? [];

        return is_array($options) ? array_values($options) : [];
    }

    public function hasInstallOptions(): bool
    {
        return $this->installOptions() !== [];
    }

    /**
     * @return array<string, string>
     */
    public function defaultInstallOptionValues(): array
    {
        $values = [];

        foreach ($this->installOptions() as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $values[$name] = (string) ($field['default'] ?? '');
        }

        return $values;
    }

    public function databaseAutoProvision(): bool
    {
        if ((bool) ($this->manifest['database']['auto_provision'] ?? false)) {
            return true;
        }

        return array_key_exists($this->slug, (array) config('applications.database_provision', []));
    }

    public function databaseNamePrefix(): string
    {
        $fromManifest = (string) ($this->manifest['database']['name_prefix'] ?? '');
        if ($fromManifest !== '') {
            return $fromManifest;
        }

        $fromConfig = config('applications.database_provision.'.$this->slug);

        if (is_array($fromConfig) && isset($fromConfig['name_prefix'])) {
            return (string) $fromConfig['name_prefix'];
        }

        return $this->slug;
    }

    public function needsInstallWizard(): bool
    {
        return $this->hasInstallOptions() || $this->databaseAutoProvision();
    }

    public function iconUrl(): string
    {
        return app(ApplicationIcon::class)->url($this);
    }

    public function iconFallbackDataUri(): string
    {
        return app(ApplicationIcon::class)->fallbackDataUri($this);
    }
}

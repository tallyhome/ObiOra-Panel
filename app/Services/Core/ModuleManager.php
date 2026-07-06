<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\DTOs\ModuleManifest;
use App\Enums\ModuleStatus;
use App\Models\PanelModule;
use App\Support\ManifestParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class ModuleManager
{
    public function __construct(
        private readonly ManifestParser $manifestParser,
    ) {}

    /**
     * @return Collection<int, ModuleManifest>
     */
    public function discover(): Collection
    {
        $cacheKey = (string) config('modules.cache.key', 'obiora.modules');

        if (config('modules.cache.enabled', true)) {
            return Cache::remember($cacheKey, (int) config('modules.cache.lifetime', 3600), fn () => $this->scanModules());
        }

        return $this->scanModules();
    }

    /**
     * @return Collection<int, ModuleManifest>
     */
    private function scanModules(): Collection
    {
        $path = (string) config('modules.path');

        if (! File::isDirectory($path)) {
            return collect();
        }

        return collect(File::directories($path))
            ->filter(fn (string $dir) => File::exists($dir.DIRECTORY_SEPARATOR.'module.json'))
            ->map(fn (string $dir) => $this->manifestParser->parseModule($dir));
    }

    public function enable(string $slug): PanelModule
    {
        $manifest = $this->getManifest($slug);

        foreach ($manifest->dependencies as $dependency) {
            if (! $this->isEnabled($dependency)) {
                throw new RuntimeException("Dependency [{$dependency}] must be enabled first.");
            }
        }

        return PanelModule::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $manifest->name,
                'version' => $manifest->version,
                'status' => ModuleStatus::Enabled,
                'enabled_at' => now(),
            ],
        );
    }

    public function disable(string $slug): PanelModule
    {
        $module = PanelModule::query()->where('slug', $slug)->firstOrFail();

        $module->update([
            'status' => ModuleStatus::Disabled,
            'enabled_at' => null,
        ]);

        return $module->fresh() ?? $module;
    }

    public function isEnabled(string $slug): bool
    {
        return PanelModule::query()
            ->where('slug', $slug)
            ->where('status', ModuleStatus::Enabled)
            ->exists();
    }

    public function getManifest(string $slug): ModuleManifest
    {
        $path = (string) config('modules.path').DIRECTORY_SEPARATOR.ucfirst($slug);

        if (! File::isDirectory($path)) {
            $path = $this->resolveModulePath($slug);
        }

        return $this->manifestParser->parseModule($path);
    }

    private function resolveModulePath(string $slug): string
    {
        $directories = File::directories((string) config('modules.path'));

        foreach ($directories as $directory) {
            if (! File::exists($directory.DIRECTORY_SEPARATOR.'module.json')) {
                continue;
            }

            $manifest = $this->manifestParser->parseModule($directory);

            if ($manifest->slug === $slug) {
                return $directory;
            }
        }

        throw new RuntimeException("Module [{$slug}] not found.");
    }
}

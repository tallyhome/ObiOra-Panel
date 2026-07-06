<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Core\ModuleManager;
use App\Support\ManifestParser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ManifestParser::class);
        $this->app->singleton(ModuleManager::class);

        $this->registerModuleProviders();
    }

    public function boot(): void
    {
        //
    }

    private function registerModuleProviders(): void
    {
        $modulesPath = (string) config('modules.path');

        if (! File::isDirectory($modulesPath)) {
            return;
        }

        foreach (File::directories($modulesPath) as $directory) {
            $providerPath = $directory.'/Providers';

            if (! File::isDirectory($providerPath)) {
                continue;
            }

            foreach (File::files($providerPath) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $namespace = (string) config('modules.namespace');
                $moduleName = basename($directory);
                $providerName = $file->getFilenameWithoutExtension();
                $class = "{$namespace}\\{$moduleName}\\Providers\\{$providerName}";

                if (class_exists($class)) {
                    $this->app->register($class);
                }
            }
        }
    }
}

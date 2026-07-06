<?php

declare(strict_types=1);

namespace Modules\Plugins\Providers;

use App\Services\Applications\ApplicationCatalog;
use App\Services\Applications\ApplicationManager;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Plugins\Livewire\PluginMarketplace;

class PluginsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApplicationCatalog::class);
        $this->app->singleton(ApplicationManager::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'plugins');

        Livewire::component('plugins.plugin-marketplace', PluginMarketplace::class);
    }
}

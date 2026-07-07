<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\LicenseValidatorInterface;
use App\Contracts\SystemExecutorInterface;
use App\Services\Core\LicenseManager;
use App\Services\Core\ServerManager;
use App\Services\Core\ModuleManager;
use App\Services\Core\UpdateManager;
use App\Support\InstalledVersion;
use App\Services\System\LocalExecutor;
use App\Livewire\Modules\ModuleStubIndex;
use App\Support\ModuleStubRegistry;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemExecutorInterface::class, LocalExecutor::class);
        $this->app->singleton(LicenseValidatorInterface::class, LicenseManager::class);
        $this->app->singleton(ServerManager::class);
    }

    public function boot(): void
    {
        Livewire::component('modules.stub-index', ModuleStubIndex::class);

        View::composer('partials.sidebar', function ($view): void {
            $updateAvailable = false;

            if (auth()->check()) {
                $check = $this->app->make(UpdateManager::class)->checkForUpdates();
                $updateAvailable = (bool) ($check['available'] ?? false);
            }

            $view->with('updateAvailable', $updateAvailable);
            $view->with('panelVersion', $this->app->make(InstalledVersion::class)->current());
            $view->with(
                'monitoringEnabled',
                auth()->check() && $this->app->make(ModuleManager::class)->isEnabled('monitoring'),
            );
            $view->with('stubModules', ModuleStubRegistry::all());
            $view->with('realtimeEnabled', \App\Support\Realtime::enabled());
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\LicenseValidatorInterface;
use App\Contracts\SystemExecutorInterface;
use App\Events\CrashAnalyzer\CrashDetected;
use App\Events\CrashAnalyzer\UnexpectedRebootDetected;
use App\Listeners\CrashAnalyzer\DispatchCrashNotifications;
use App\Services\Core\LicenseManager;
use App\Services\Core\ServerManager;
use App\Services\Core\ModuleManager;
use App\Services\Core\UpdateManager;
use App\Services\Core\UpdateRecovery;
use App\Support\InstalledVersion;
use App\Services\System\LocalExecutor;
use App\Livewire\Modules\ModuleStubIndex;
use App\Support\InfrastructureModuleRegistry;
use App\Support\ModuleStubRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
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
        Gate::before(function ($user, string $ability) {
            if ($user !== null && method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });

        Livewire::component('modules.stub-index', ModuleStubIndex::class);

        Event::listen(CrashDetected::class, [DispatchCrashNotifications::class, 'handleCrash']);
        Event::listen(UnexpectedRebootDetected::class, [DispatchCrashNotifications::class, 'handleReboot']);

        View::composer('partials.sidebar', function ($view): void {
            if (auth()->check()) {
                $this->app->make(UpdateRecovery::class)->recoverStale(20);
            }

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
            $view->with('stubModules', ModuleStubRegistry::infrastructure());
            $view->with('infraModules', InfrastructureModuleRegistry::implemented());
            $view->with('realtimeEnabled', \App\Support\Realtime::enabled());
        });
    }
}

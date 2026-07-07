<?php

declare(strict_types=1);

namespace Modules\Updates\Providers;

use App\Services\Core\AdminLicenceClient;
use App\Services\Core\LicenseService;
use App\Services\Core\PanelUpdater;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Updates\Livewire\SettingsIndex;

class UpdatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminLicenceClient::class);
        $this->app->singleton(LicenseService::class);
        $this->app->singleton(PanelUpdater::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'updates');
        Livewire::component('updates.settings-index', SettingsIndex::class);
    }
}

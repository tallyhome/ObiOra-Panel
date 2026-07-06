<?php

declare(strict_types=1);

namespace Modules\Services\Providers;

use App\Services\System\ServiceManager;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Services\Livewire\ServiceList;

class ServicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ServiceManager::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'services');
        Livewire::component('services.service-list', ServiceList::class);
    }
}

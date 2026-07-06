<?php

declare(strict_types=1);

namespace Modules\Dashboard\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Dashboard\Livewire\DashboardIndex;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'dashboard');
        Livewire::component('dashboard-index', DashboardIndex::class);
    }
}

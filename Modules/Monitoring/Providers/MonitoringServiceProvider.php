<?php

declare(strict_types=1);

namespace Modules\Monitoring\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Monitoring\Livewire\MonitoringIndex;

class MonitoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'monitoring');
        Livewire::component('monitoring.monitoring-index', MonitoringIndex::class);
    }
}

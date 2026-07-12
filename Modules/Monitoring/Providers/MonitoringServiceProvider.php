<?php

declare(strict_types=1);

namespace Modules\Monitoring\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Monitoring\Livewire\DoctorSuiteIndex;
use Modules\Monitoring\Livewire\MonitoringAlertsIndex;
use Modules\Monitoring\Livewire\MonitoringHubIndex;
use Modules\Monitoring\Livewire\MonitoringIncidentsIndex;
use Modules\Monitoring\Livewire\MonitoringIndex;
use Modules\Monitoring\Livewire\MonitoringMonitorShow;
use Modules\Monitoring\Livewire\MonitoringMonitorsIndex;
use Modules\Monitoring\Livewire\MonitoringServerMetricsIndex;
use Modules\Monitoring\Livewire\MonitoringPreferencesIndex;
use Modules\Monitoring\Livewire\MonitoringServersIndex;
use Modules\Monitoring\Livewire\MonitoringStatusPageSettings;
use Modules\Monitoring\Livewire\PublicStatusPage;

class MonitoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'monitoring');
        Livewire::component('monitoring.hub-index', MonitoringHubIndex::class);
        Livewire::component('monitoring.monitoring-index', MonitoringIndex::class);
        Livewire::component('monitoring.servers-index', MonitoringServersIndex::class);
        Livewire::component('monitoring.monitors-index', MonitoringMonitorsIndex::class);
        Livewire::component('monitoring.monitor-show', MonitoringMonitorShow::class);
        Livewire::component('monitoring.server-metrics-index', MonitoringServerMetricsIndex::class);
        Livewire::component('monitoring.incidents-index', MonitoringIncidentsIndex::class);
        Livewire::component('monitoring.alerts-index', MonitoringAlertsIndex::class);
        Livewire::component('monitoring.preferences-index', MonitoringPreferencesIndex::class);
        Livewire::component('monitoring.status-page-settings', MonitoringStatusPageSettings::class);
        Livewire::component('monitoring.public-status-page', PublicStatusPage::class);
        Livewire::component('doctor.index', DoctorSuiteIndex::class);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Cluster\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Cluster\Livewire\ClusterIndex;

class ClusterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::component('cluster.index', ClusterIndex::class);
    }
}

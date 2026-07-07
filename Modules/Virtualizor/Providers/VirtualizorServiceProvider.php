<?php

declare(strict_types=1);

namespace Modules\Virtualizor\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Virtualizor\Livewire\VirtualizorIndex;

class VirtualizorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'virtualizor');
        Livewire::component('virtualizor.index', VirtualizorIndex::class);
    }
}

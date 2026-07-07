<?php

declare(strict_types=1);

namespace Modules\Applications\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Applications\Livewire\ApplicationsIndex;

class ApplicationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::component('applications.index', ApplicationsIndex::class);
    }
}

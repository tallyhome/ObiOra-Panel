<?php

declare(strict_types=1);

namespace Modules\Apache\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Apache\Livewire\ApacheIndex;

class ApacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::component('apache.index', ApacheIndex::class);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Nginx\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Nginx\Livewire\NginxIndex;

class NginxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::component('nginx.index', NginxIndex::class);
    }
}

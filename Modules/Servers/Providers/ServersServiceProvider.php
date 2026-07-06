<?php

declare(strict_types=1);

namespace Modules\Servers\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Servers\Livewire\ServerCreate;
use Modules\Servers\Livewire\ServerList;
use Modules\Servers\Livewire\ServerShow;

class ServersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'servers');
        Livewire::component('servers.server-list', ServerList::class);
        Livewire::component('servers.server-create', ServerCreate::class);
        Livewire::component('servers.server-show', ServerShow::class);
    }
}

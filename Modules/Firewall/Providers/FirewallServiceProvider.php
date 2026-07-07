<?php

declare(strict_types=1);

namespace Modules\Firewall\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Firewall\Livewire\FirewallIndex;

class FirewallServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::component('firewall.index', FirewallIndex::class);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Security\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Security\Livewire\SecurityIndex;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'security');
        Livewire::component('security.index', SecurityIndex::class);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Users\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Users\Livewire\ProfileIndex;
use Modules\Users\Livewire\UserIndex;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'users');
        Livewire::component('users.index', UserIndex::class);
        Livewire::component('profile.index', ProfileIndex::class);
    }
}

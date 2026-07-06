<?php

declare(strict_types=1);

namespace Modules\MySQL\Providers;

use App\Services\Database\DatabaseManager;
use App\Services\Database\DatabaseProvisioner;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\MySQL\Livewire\DatabaseCreate;
use Modules\MySQL\Livewire\DatabaseList;
use Modules\MySQL\Livewire\DatabaseShow;

class MySQLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseProvisioner::class);
        $this->app->singleton(DatabaseManager::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'mysql');

        Livewire::component('mysql.database-list', DatabaseList::class);
        Livewire::component('mysql.database-create', DatabaseCreate::class);
        Livewire::component('mysql.database-show', DatabaseShow::class);
    }
}

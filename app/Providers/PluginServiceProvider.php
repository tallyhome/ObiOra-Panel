<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Core\PluginManager;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginManager::class);
    }

    public function boot(): void
    {
        //
    }
}

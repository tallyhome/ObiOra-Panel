<?php

declare(strict_types=1);

namespace Modules\Docker\Providers;

use App\Services\Docker\DockerManager;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Docker\Livewire\DockerIndex;

class DockerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DockerManager::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'docker');

        Livewire::component('docker.docker-index', DockerIndex::class);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Redis\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Redis\Livewire\RedisIndex;

class RedisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::component('redis.index', RedisIndex::class);
    }
}

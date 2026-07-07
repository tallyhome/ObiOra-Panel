<?php

declare(strict_types=1);

namespace Modules\SSL\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\SSL\Livewire\SslIndex;

class SSLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::component('ssl.index', SslIndex::class);
    }
}

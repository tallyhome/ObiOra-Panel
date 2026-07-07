<?php

declare(strict_types=1);

namespace Modules\DNS\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\DNS\Livewire\DnsIndex;

class DNSServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::component('dns.index', DnsIndex::class);
    }
}

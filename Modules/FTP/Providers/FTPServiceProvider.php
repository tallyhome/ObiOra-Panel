<?php

declare(strict_types=1);

namespace Modules\FTP\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\FTP\Livewire\FtpIndex;

class FTPServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::component('ftp.index', FtpIndex::class);
    }
}

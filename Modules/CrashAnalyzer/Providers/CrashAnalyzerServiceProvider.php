<?php

declare(strict_types=1);

namespace Modules\CrashAnalyzer\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\CrashAnalyzer\Livewire\CrashAnalyzerIndex;

class CrashAnalyzerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'crash-analyzer');
        Livewire::component('crash-analyzer.index', CrashAnalyzerIndex::class);
    }
}

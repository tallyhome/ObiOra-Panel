<?php

declare(strict_types=1);

namespace Modules\Websites\Providers;

use App\Services\Web\WebsiteManager;
use App\Services\Web\WebsiteProvisioner;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Websites\Livewire\WebsiteCreate;
use Modules\Websites\Livewire\WebsiteList;
use Modules\Websites\Livewire\WebsiteShow;

class WebsitesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WebsiteProvisioner::class);
        $this->app->singleton(WebsiteManager::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'websites');

        Livewire::component('websites.website-list', WebsiteList::class);
        Livewire::component('websites.website-create', WebsiteCreate::class);
        Livewire::component('websites.website-show', WebsiteShow::class);
    }
}

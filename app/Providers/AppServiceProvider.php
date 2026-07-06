<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\LicenseValidatorInterface;
use App\Contracts\SystemExecutorInterface;
use App\Services\Core\LicenseManager;
use App\Services\Core\ServerManager;
use App\Services\System\LocalExecutor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemExecutorInterface::class, LocalExecutor::class);
        $this->app->singleton(LicenseValidatorInterface::class, LicenseManager::class);
        $this->app->singleton(ServerManager::class);
    }

    public function boot(): void
    {
        //
    }
}

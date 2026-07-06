<?php

declare(strict_types=1);

namespace Modules\Backup\Providers;

use App\Services\Backup\BackupManager;
use App\Services\Backup\BackupProvisioner;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Backup\Livewire\BackupCreate;
use Modules\Backup\Livewire\BackupList;
use Modules\Backup\Livewire\BackupShow;

class BackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BackupProvisioner::class);
        $this->app->singleton(BackupManager::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'backup');

        Livewire::component('backup.backup-list', BackupList::class);
        Livewire::component('backup.backup-create', BackupCreate::class);
        Livewire::component('backup.backup-show', BackupShow::class);
    }
}

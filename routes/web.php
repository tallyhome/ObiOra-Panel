<?php

declare(strict_types=1);

use App\Http\Controllers\ApplicationIconController;
use App\Livewire\Auth\Login;
use App\Livewire\Setup\CreateAdmin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Livewire\DashboardIndex;
use Modules\Servers\Livewire\ServerCreate;
use Modules\Servers\Livewire\ServerList;
use Modules\Servers\Livewire\ServerShow;

Route::middleware('setup')->group(function () {
    Route::get('/setup', CreateAdmin::class)->name('setup');
    Route::get('/login', Login::class)->name('login')->middleware('guest');
});

Route::post('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['setup', 'auth', 'server'])->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', DashboardIndex::class)->name('dashboard');

    Route::prefix('servers')->name('servers.')->group(function () {
        Route::get('/', ServerList::class)->name('index');
        Route::get('/create', ServerCreate::class)->name('create');
        Route::get('/{server}', ServerShow::class)->name('show');
    });

    Route::get('/services', \Modules\Services\Livewire\ServiceList::class)->name('services.index');

    Route::prefix('websites')->name('websites.')->group(function () {
        Route::get('/', \Modules\Websites\Livewire\WebsiteList::class)->name('index');
        Route::get('/create', \Modules\Websites\Livewire\WebsiteCreate::class)->name('create');
        Route::get('/{website}', \Modules\Websites\Livewire\WebsiteShow::class)->name('show');
    });

    Route::prefix('databases')->name('databases.')->group(function () {
        Route::get('/', \Modules\MySQL\Livewire\DatabaseList::class)->name('index');
        Route::get('/create', \Modules\MySQL\Livewire\DatabaseCreate::class)->name('create');
        Route::get('/{database}', \Modules\MySQL\Livewire\DatabaseShow::class)->name('show');
    });

    Route::get('/docker', \Modules\Docker\Livewire\DockerIndex::class)->name('docker.index');

    Route::prefix('backups')->name('backups.')->group(function () {
        Route::get('/', \Modules\Backup\Livewire\BackupList::class)->name('index');
        Route::get('/create', \Modules\Backup\Livewire\BackupCreate::class)->name('create');
        Route::get('/{backup}', \Modules\Backup\Livewire\BackupShow::class)->name('show');
    });

    Route::get('/plugins', \Modules\Plugins\Livewire\PluginMarketplace::class)->name('plugins.index');
    Route::get('/plugins/icons/{slug}', ApplicationIconController::class)->name('plugins.icon');

    Route::get('/settings', \Modules\Updates\Livewire\SettingsIndex::class)->name('settings.index');
});

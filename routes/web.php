<?php

declare(strict_types=1);

use App\Livewire\Modules\ModuleStubIndex;
use App\Support\ModuleStubRegistry;
use App\Http\Controllers\Api\DiagnosticReportController;
use App\Http\Controllers\ApplicationIconController;
use App\Http\Controllers\MarketplaceInstallSetupController;
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

Route::get('/install/doctor-agent.sh', function () {
    $path = base_path('agent/scripts/bootstrap-doctor-agent.sh');

    abort_unless(is_readable($path), 404);

    return response(
        (string) file_get_contents($path),
        200,
        ['Content-Type' => 'text/x-shellscript; charset=utf-8'],
    );
})->name('install.doctor-agent');

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
    Route::post('/plugins/install-setup', [MarketplaceInstallSetupController::class, 'store'])->name('plugins.install-setup');
    Route::get('/plugins/icons/{slug}', ApplicationIconController::class)->name('plugins.icon');

    Route::get('/monitoring', \Modules\Monitoring\Livewire\MonitoringIndex::class)->name('monitoring.index');

    Route::get('/ai', \Modules\AI\Livewire\AiAssistant::class)->name('ai.index');
    Route::redirect('/modules/ai', '/ai');

    Route::get('/ssl', \Modules\SSL\Livewire\SslIndex::class)->name('ssl.index');
    Route::get('/firewall', \Modules\Firewall\Livewire\FirewallIndex::class)->name('firewall.index');
    Route::get('/users', \Modules\Users\Livewire\UserIndex::class)->name('users.index');
    Route::get('/profile', \Modules\Users\Livewire\ProfileIndex::class)->name('profile.index');
    Route::get('/nginx', \Modules\Nginx\Livewire\NginxIndex::class)->name('nginx.index');
    Route::get('/redis', \Modules\Redis\Livewire\RedisIndex::class)->name('redis.index');
    Route::get('/apache', \Modules\Apache\Livewire\ApacheIndex::class)->name('apache.index');
    Route::get('/ftp', \Modules\FTP\Livewire\FtpIndex::class)->name('ftp.index');
    Route::get('/dns', \Modules\DNS\Livewire\DnsIndex::class)->name('dns.index');
    Route::get('/applications', \Modules\Applications\Livewire\ApplicationsIndex::class)->name('applications.index');
    Route::get('/virtualizor', \Modules\Virtualizor\Livewire\VirtualizorIndex::class)->name('virtualizor.index');
    Route::get('/cluster', \Modules\Cluster\Livewire\ClusterIndex::class)->name('cluster.index');
    Route::get('/doctor', \Modules\Monitoring\Livewire\DoctorSuiteIndex::class)->name('doctor.index');

    Route::redirect('/modules/ssl', '/ssl');
    Route::redirect('/modules/firewall', '/firewall');
    Route::redirect('/modules/users', '/users');

    Route::prefix('api/monitoring')->name('monitoring.api.')->group(function () {
        Route::get('/fleet', [\App\Http\Controllers\Api\MonitoringFleetController::class, 'fleet'])->name('fleet');
        Route::get('/stream', [\App\Http\Controllers\MonitoringStreamController::class, 'stream'])->name('stream');
        Route::get('/servers/{server}/ping-history', [\App\Http\Controllers\Api\MonitoringFleetController::class, 'pingHistory'])->name('ping-history');
        Route::get('/servers/{server}/score-history', [\App\Http\Controllers\Api\MonitoringFleetController::class, 'scoreHistory'])->name('score-history');
        Route::get('/servers/{server}/compare', [\App\Http\Controllers\Api\MonitoringFleetController::class, 'compare'])->name('compare');
        Route::post('/alerts/{alert}/read', [\App\Http\Controllers\Api\MonitoringFleetController::class, 'markAlertRead'])->name('alerts.read');
        Route::get('/servers/{server}/install-command', [\App\Http\Controllers\Api\MonitoringFleetController::class, 'installCommand'])->name('install-command');
        Route::get('/servers/{server}/diagnostics/latest', [DiagnosticReportController::class, 'latest'])->name('diagnostics.latest');
        Route::get('/servers/{server}/diagnostics', [DiagnosticReportController::class, 'index'])->name('diagnostics.index');
    });

    $stubSlugs = array_keys(ModuleStubRegistry::infrastructure());
    if ($stubSlugs !== []) {
        Route::get('/modules/{slug}', ModuleStubIndex::class)
            ->where('slug', implode('|', $stubSlugs))
            ->name('modules.stub');
    }

    Route::get('/settings', \Modules\Updates\Livewire\SettingsIndex::class)->name('settings.index');
});

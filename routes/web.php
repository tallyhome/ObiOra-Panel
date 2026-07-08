<?php

declare(strict_types=1);

use App\Livewire\Modules\ModuleStubIndex;
use App\Support\ModuleStubRegistry;
use App\Http\Controllers\Api\DiagnosticReportController;
use App\Http\Controllers\ApplicationIconController;
use App\Http\Controllers\MarketplaceInstallSetupController;
use App\Http\Controllers\CrashAnalyzerExportController;
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

Route::get('/install/crash-analyzer.sh', function () {
    $path = base_path('agent/scripts/install-crash-analyzer.sh');

    abort_unless(is_readable($path), 404);

    return response(
        (string) file_get_contents($path),
        200,
        ['Content-Type' => 'text/x-shellscript; charset=utf-8'],
    );
})->name('install.crash-analyzer');

Route::middleware(['setup', 'auth', 'demo.active', 'server'])->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/profile', \Modules\Users\Livewire\ProfileIndex::class)->name('profile.index');

    Route::middleware('permission:dashboard.view')->get('/dashboard', DashboardIndex::class)->name('dashboard');

    Route::get('/servers/create', ServerCreate::class)->middleware('permission:servers.manage')->name('servers.create');

    Route::middleware('permission:servers.view')->prefix('servers')->name('servers.')->group(function () {
        Route::get('/', ServerList::class)->name('index');
        Route::get('/{server}', ServerShow::class)->name('show');
    });

    Route::middleware('permission:services.view')->get('/services', \Modules\Services\Livewire\ServiceList::class)->name('services.index');

    Route::get('/websites/create', \Modules\Websites\Livewire\WebsiteCreate::class)
        ->middleware('permission:websites.manage')
        ->name('websites.create');

    Route::middleware('permission:websites.view')->prefix('websites')->name('websites.')->group(function () {
        Route::get('/', \Modules\Websites\Livewire\WebsiteList::class)->name('index');
        Route::get('/{website}', \Modules\Websites\Livewire\WebsiteShow::class)->name('show');
    });

    Route::get('/databases/create', \Modules\MySQL\Livewire\DatabaseCreate::class)
        ->middleware('permission:databases.manage')
        ->name('databases.create');

    Route::middleware('permission:databases.view')->prefix('databases')->name('databases.')->group(function () {
        Route::get('/', \Modules\MySQL\Livewire\DatabaseList::class)->name('index');
        Route::get('/{database}', \Modules\MySQL\Livewire\DatabaseShow::class)->name('show');
    });

    Route::middleware('permission:docker.view')->get('/docker', \Modules\Docker\Livewire\DockerIndex::class)->name('docker.index');

    Route::get('/backups/create', \Modules\Backup\Livewire\BackupCreate::class)
        ->middleware('permission:backups.manage')
        ->name('backups.create');

    Route::middleware('permission:backups.view')->prefix('backups')->name('backups.')->group(function () {
        Route::get('/', \Modules\Backup\Livewire\BackupList::class)->name('index');
        Route::get('/{backup}', \Modules\Backup\Livewire\BackupShow::class)->name('show');
    });

    Route::middleware('permission:plugins.view')->group(function () {
        Route::get('/plugins', \Modules\Plugins\Livewire\PluginMarketplace::class)->name('plugins.index');
        Route::get('/plugins/icons/{slug}', ApplicationIconController::class)->name('plugins.icon');
    });
    Route::post('/plugins/install-setup', [MarketplaceInstallSetupController::class, 'store'])
        ->middleware('permission:plugins.manage')
        ->name('plugins.install-setup');

    Route::middleware('permission:monitoring.view')->group(function () {
        Route::get('/monitoring', \Modules\Monitoring\Livewire\MonitoringIndex::class)->name('monitoring.index');

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
    });

    Route::middleware('permission:ai.view')->get('/ai', \Modules\AI\Livewire\AiAssistant::class)->name('ai.index');
    Route::redirect('/modules/ai', '/ai');

    Route::middleware('permission:modules.view')->group(function () {
        Route::get('/ssl', \Modules\SSL\Livewire\SslIndex::class)->name('ssl.index');
        Route::get('/nginx', \Modules\Nginx\Livewire\NginxIndex::class)->name('nginx.index');
        Route::get('/redis', \Modules\Redis\Livewire\RedisIndex::class)->name('redis.index');
        Route::get('/apache', \Modules\Apache\Livewire\ApacheIndex::class)->name('apache.index');
        Route::get('/ftp', \Modules\FTP\Livewire\FtpIndex::class)->name('ftp.index');
        Route::get('/dns', \Modules\DNS\Livewire\DnsIndex::class)->name('dns.index');
        Route::get('/applications', \Modules\Applications\Livewire\ApplicationsIndex::class)->name('applications.index');
        Route::get('/virtualizor', \Modules\Virtualizor\Livewire\VirtualizorIndex::class)->name('virtualizor.index');
        Route::get('/cluster', \Modules\Cluster\Livewire\ClusterIndex::class)->name('cluster.index');
        Route::get('/doctor', \Modules\Monitoring\Livewire\DoctorSuiteIndex::class)->name('doctor.index');
        Route::get('/crash-analyzer', \Modules\CrashAnalyzer\Livewire\CrashAnalyzerIndex::class)->name('crash-analyzer.index');

        $stubSlugs = array_keys(ModuleStubRegistry::infrastructure());
        if ($stubSlugs !== []) {
            Route::get('/modules/{slug}', ModuleStubIndex::class)
                ->where('slug', implode('|', $stubSlugs))
                ->name('modules.stub');
        }
    });

    Route::get('/firewall', \Modules\Firewall\Livewire\FirewallIndex::class)
        ->middleware('permission:modules.view')
        ->name('firewall.index');

    Route::get('/users', \Modules\Users\Livewire\UserIndex::class)
        ->middleware('permission:users.view')
        ->name('users.index');

    Route::prefix('crash-analyzer')->name('crash-analyzer.')->group(function () {
        Route::middleware('permission:monitoring.view')->group(function () {
            Route::get('/servers/{server}/export/json', [CrashAnalyzerExportController::class, 'json'])
                ->name('export.json');
            Route::get('/servers/{server}/export/csv', [CrashAnalyzerExportController::class, 'csv'])
                ->name('export.csv');
            Route::get('/servers/{server}/reports/{report}/export', [CrashAnalyzerExportController::class, 'pdf'])
                ->name('export.pdf');
        });
    });

    Route::redirect('/modules/ssl', '/ssl');
    Route::redirect('/modules/firewall', '/firewall');
    Route::redirect('/modules/users', '/users');

    Route::middleware('permission:updates.view')->get('/settings', \Modules\Updates\Livewire\SettingsIndex::class)->name('settings.index');
});

<?php

declare(strict_types=1);

namespace Modules\Plugins\Livewire;

use App\Models\InstalledApplication;
use App\Services\Applications\ApplicationCatalog;
use App\Services\Applications\ApplicationManager;
use App\Services\Core\ServerManager;
use App\Services\Docker\DockerManager;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Marketplace')]
final class PluginMarketplace extends Component
{
    public string $category = '';

    public string $search = '';

    public ?string $installingSlug = null;

    public int $installProgress = 0;

    public string $installProgressMessage = '';

    public bool $installRunning = false;

    public ?int $infoAppId = null;

    public string $appLogOutput = '';

    /** @var array<string, mixed> */
    public array $appInfo = [];

    public ?string $setupSlug = null;

    /** @var array<string, string> */
    public array $setupValues = [];

    public function mount(ApplicationManager $manager, ServerManager $serverManager): void
    {
        $this->resumeInstall($manager, $serverManager);
    }

    #[On('server-changed')]
    public function onServerChanged(ApplicationManager $manager, ServerManager $serverManager): void
    {
        $this->infoAppId = null;
        $this->appLogOutput = '';
        $this->appInfo = [];
        $this->resumeInstall($manager, $serverManager);
    }

    public function install(string $slug, ApplicationManager $manager, ServerManager $serverManager, ApplicationCatalog $catalog): void
    {
        if ($this->installRunning) {
            return;
        }

        $package = $catalog->find($slug);

        if ($package !== null && $package->hasInstallOptions()) {
            $this->openInstallSetup($slug, $catalog);

            return;
        }

        $this->startInstall($slug, $manager, $serverManager);
    }

    public function openInstallSetup(string $slug, ApplicationCatalog $catalog): void
    {
        $package = $catalog->find($slug);

        if ($package === null) {
            return;
        }

        $this->setupSlug = $slug;
        $this->setupValues = $package->defaultInstallOptionValues();
    }

    public function cancelInstallSetup(): void
    {
        $this->setupSlug = null;
        $this->setupValues = [];
    }

    public function confirmInstallSetup(ApplicationManager $manager, ServerManager $serverManager, ApplicationCatalog $catalog): void
    {
        if ($this->setupSlug === null || $this->installRunning) {
            return;
        }

        $package = $catalog->find($this->setupSlug);

        if ($package === null) {
            $this->cancelInstallSetup();

            return;
        }

        try {
            $server = $serverManager->getCurrentServer();

            if ($server === null) {
                throw new \InvalidArgumentException('Aucun serveur sélectionné.');
            }

            $options = $manager->validateInstallOptions($package, $this->setupValues);
            $slug = $this->setupSlug;
            $this->cancelInstallSetup();
            $this->startInstall($slug, $manager, $serverManager, $options);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    /**
     * @param  array<string, string>  $options
     */
    private function startInstall(string $slug, ApplicationManager $manager, ServerManager $serverManager, array $options = []): void
    {
        try {
            $server = $serverManager->getCurrentServer();

            if ($server === null) {
                throw new \InvalidArgumentException('Aucun serveur sélectionné.');
            }

            $manager->queueInstall($slug, $server, $options);
            $this->installingSlug = $slug;
            $this->installRunning = true;
            $this->installProgress = 3;
            $this->installProgressMessage = 'Installation démarrée…';
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function uninstall(int $id, ApplicationManager $manager): void
    {
        if ($this->installRunning) {
            $this->dispatch('notify', type: 'warning', message: 'Une opération est déjà en cours.');

            return;
        }

        $app = InstalledApplication::query()->findOrFail($id);

        try {
            $this->installingSlug = $app->slug;
            $this->installRunning = true;
            $this->installProgress = 3;
            $this->installProgressMessage = 'Désinstallation démarrée…';
            $manager->queueUninstall($app);
            $this->infoAppId = null;
        } catch (\InvalidArgumentException $e) {
            $this->installRunning = false;
            $this->installingSlug = null;
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function pollInstall(ApplicationManager $manager, ServerManager $serverManager): void
    {
        $server = $serverManager->getCurrentServer();

        if ($server === null || $this->installingSlug === null) {
            $this->installRunning = false;

            return;
        }

        $status = Cache::get($manager->progressCacheKey($server->id, $this->installingSlug));

        if (! is_array($status)) {
            $this->installRunning = false;
            $this->installingSlug = null;

            return;
        }

        $this->installProgress = (int) ($status['progress'] ?? 0);
        $this->installProgressMessage = (string) ($status['message'] ?? '');
        $this->installRunning = (bool) ($status['running'] ?? false);

        if (! $this->installRunning && ($status['success'] ?? null) !== null) {
            $type = ($status['success'] ?? false) ? 'success' : 'danger';
            $this->dispatch('notify', type: $type, message: $this->installProgressMessage);
            $this->installingSlug = null;
        }
    }

    public function appAction(int $id, string $action, ApplicationManager $manager): void
    {
        $app = InstalledApplication::query()->findOrFail($id);

        try {
            $result = $manager->appControl($app, $action);
            $this->dispatch('notify', type: $result['success'] ? 'success' : 'danger', message: $result['success']
                ? ucfirst($action).' effectué.'
                : $result['output']);

            if ($this->infoAppId === $id) {
                $this->appInfo = $manager->appInfo($app);
            }
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function showAppInfo(int $id, ApplicationManager $manager): void
    {
        $app = InstalledApplication::query()->findOrFail($id);
        $this->infoAppId = $id;
        $this->appInfo = $manager->appInfo($app);
        $this->appLogOutput = '';
    }

    public function showAppLogs(int $id, ApplicationManager $manager): void
    {
        $app = InstalledApplication::query()->findOrFail($id);
        $this->infoAppId = $id;
        $this->appInfo = $manager->appInfo($app);
        $this->appLogOutput = $manager->appLogs($app);
    }

    public function closeAppInfo(): void
    {
        $this->infoAppId = null;
        $this->appInfo = [];
        $this->appLogOutput = '';
    }

    public function render(ApplicationCatalog $catalog, ApplicationManager $manager, ServerManager $serverManager, DockerManager $dockerManager)
    {
        $server = $serverManager->getCurrentServer();
        $installed = $manager->installed($server);
        $installedSlugs = $installed
            ->where('status', \App\Enums\ApplicationStatus::Installed)
            ->pluck('slug')
            ->all();
        $categories = $catalog->categories();

        $installedApps = $installed
            ->where('status', \App\Enums\ApplicationStatus::Installed)
            ->map(function (InstalledApplication $app) use ($manager) {
                return array_merge(
                    ['app' => $app],
                    $manager->appInfo($app),
                );
            });

        $filtered = $catalog->all()
            ->when($this->category, fn ($c) => $c->filter(fn ($p) => $p->category() === $this->category))
            ->when($this->search, fn ($c) => $c->filter(
                fn ($p) => str_contains(strtolower($p->name()), strtolower($this->search))
                    || str_contains(strtolower($p->description()), strtolower($this->search))
            ));

        return view('plugins::livewire.plugin-marketplace', [
            'serverName' => $server?->name ?? 'Aucun',
            'dockerInstalled' => ($dockerManager->info($server)['installed'] ?? false),
            'installed' => $installed,
            'installedApps' => $installedApps,
            'filtered' => $filtered,
            'installedSlugs' => $installedSlugs,
            'categories' => $categories,
            'setupPackage' => $this->setupSlug ? $catalog->find($this->setupSlug) : null,
        ]);
    }

    private function resumeInstall(ApplicationManager $manager, ServerManager $serverManager): void
    {
        $server = $serverManager->getCurrentServer();

        if ($server === null) {
            return;
        }

        foreach ($manager->installed($server) as $app) {
            $status = Cache::get($manager->progressCacheKey($server->id, $app->slug));

            if (is_array($status) && ($status['running'] ?? false)) {
                $this->installingSlug = $app->slug;
                $this->installRunning = true;
                $this->installProgress = (int) ($status['progress'] ?? 0);
                $this->installProgressMessage = (string) ($status['message'] ?? '');

                return;
            }
        }
    }
}

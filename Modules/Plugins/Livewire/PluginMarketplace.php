<?php

declare(strict_types=1);

namespace Modules\Plugins\Livewire;

use App\Models\InstalledApplication;
use App\Services\Applications\ApplicationCatalog;
use App\Services\Applications\ApplicationManager;
use App\Services\Core\ServerManager;
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

    #[On('server-changed')]
    public function onServerChanged(): void
    {
        //
    }

    public function install(string $slug, ApplicationManager $manager, ServerManager $serverManager): void
    {
        try {
            $app = $manager->install($slug, $serverManager->getCurrentServer());
            session()->flash('success', "« {$app->name} » installé avec succès.");
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function uninstall(int $id, ApplicationManager $manager): void
    {
        $app = InstalledApplication::query()->findOrFail($id);

        try {
            $name = $app->name;
            $manager->uninstall($app);
            session()->flash('success', "« {$name} » désinstallé.");
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function render(ApplicationCatalog $catalog, ApplicationManager $manager, ServerManager $serverManager)
    {
        $server = $serverManager->getCurrentServer();
        $installed = $manager->installed($server);
        $installedSlugs = $installed->pluck('slug')->all();
        $categories = $catalog->categories();

        $filtered = $catalog->all()
            ->when($this->category, fn ($c) => $c->filter(fn ($p) => $p->category() === $this->category))
            ->when($this->search, fn ($c) => $c->filter(
                fn ($p) => str_contains(strtolower($p->name()), strtolower($this->search))
                    || str_contains(strtolower($p->description()), strtolower($this->search))
            ));

        return view('plugins::livewire.plugin-marketplace', [
            'serverName' => $server?->name ?? 'Aucun',
            'installed' => $installed,
            'filtered' => $filtered,
            'installedSlugs' => $installedSlugs,
            'categories' => $categories,
        ]);
    }
}

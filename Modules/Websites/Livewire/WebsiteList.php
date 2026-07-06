<?php

declare(strict_types=1);

namespace Modules\Websites\Livewire;

use App\Models\Website;
use App\Services\Core\ServerManager;
use App\Services\Web\WebsiteManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sites web')]
final class WebsiteList extends Component
{
    /** @var \Illuminate\Support\Collection<int, Website> */
    public $websites;

    public string $serverName = '';

    public function mount(WebsiteManager $websiteManager, ServerManager $serverManager): void
    {
        $this->loadWebsites($websiteManager, $serverManager);
    }

    #[On('server-changed')]
    public function onServerChanged(WebsiteManager $websiteManager, ServerManager $serverManager): void
    {
        $this->loadWebsites($websiteManager, $serverManager);
    }

    public function delete(int $websiteId, WebsiteManager $websiteManager): void
    {
        $website = Website::query()->findOrFail($websiteId);

        try {
            $websiteManager->delete($website);
            session()->flash('success', "Site « {$website->domain} » supprimé.");
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }

        $this->loadWebsites($websiteManager, app(ServerManager::class));
    }

    private function loadWebsites(WebsiteManager $websiteManager, ServerManager $serverManager): void
    {
        $server = $serverManager->getCurrentServer();
        $this->serverName = $server?->name ?? 'Aucun';
        $this->websites = $websiteManager->forServer($server);
    }

    public function render()
    {
        return view('websites::livewire.website-list');
    }
}

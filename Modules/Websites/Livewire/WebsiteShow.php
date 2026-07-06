<?php

declare(strict_types=1);

namespace Modules\Websites\Livewire;

use App\Models\Website;
use App\Services\Web\WebsiteManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Détail du site')]
final class WebsiteShow extends Component
{
    public Website $website;

    public string $ssl_email = '';

    public function mount(Website $website): void
    {
        $this->website = $website;
        $this->ssl_email = $website->ssl_email ?? '';
    }

    public function enableSsl(WebsiteManager $websiteManager): void
    {
        $this->validate([
            'ssl_email' => ['required', 'email'],
        ]);

        try {
            $this->website = $websiteManager->enableSsl($this->website, $this->ssl_email);
            session()->flash('success', 'Certificat SSL activé.');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function delete(WebsiteManager $websiteManager): void
    {
        $domain = $this->website->domain;

        try {
            $websiteManager->delete($this->website);
            session()->flash('success', "Site « {$domain} » supprimé.");
            $this->redirect(route('websites.index'), navigate: true);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function render()
    {
        return view('websites::livewire.website-show');
    }
}

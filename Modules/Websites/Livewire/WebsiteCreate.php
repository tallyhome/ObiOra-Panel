<?php

declare(strict_types=1);

namespace Modules\Websites\Livewire;

use App\Services\Core\ServerManager;
use App\Services\Web\WebsiteManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Créer un site web')]
final class WebsiteCreate extends Component
{
    public string $domain = '';

    public string $php_version = '8.3';

    public bool $enable_ssl = false;

    public string $ssl_email = '';

    public function mount(): void
    {
        $this->php_version = (string) config('obiora.websites.default_php', '8.3');
    }

    public function save(WebsiteManager $websiteManager, ServerManager $serverManager): void
    {
        $rules = [
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)+$/'],
            'php_version' => ['required', 'in:'.implode(',', config('obiora.websites.php_versions', ['8.3']))],
            'enable_ssl' => ['boolean'],
        ];

        if ($this->enable_ssl) {
            $rules['ssl_email'] = ['required', 'email'];
        }

        $this->validate($rules);

        try {
            $website = $websiteManager->create([
                'domain' => strtolower($this->domain),
                'php_version' => $this->php_version,
                'enable_ssl' => $this->enable_ssl,
                'ssl_email' => $this->enable_ssl ? $this->ssl_email : null,
            ], $serverManager->getCurrentServer());
        } catch (\InvalidArgumentException $e) {
            $this->addError('domain', $e->getMessage());

            return;
        }

        session()->flash('success', "Site « {$website->domain} » créé avec succès.");

        $this->redirect(route('websites.show', $website), navigate: true);
    }

    public function render()
    {
        return view('websites::livewire.website-create', [
            'phpVersions' => config('obiora.websites.php_versions', ['8.3']),
        ]);
    }
}

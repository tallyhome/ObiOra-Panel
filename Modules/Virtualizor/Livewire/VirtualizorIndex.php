<?php

declare(strict_types=1);

namespace Modules\Virtualizor\Livewire;

use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Virtualizor')]
final class VirtualizorIndex extends Component
{
    public string $apiUrl = '';

    public string $apiKey = '';

    public string $message = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('modules.view'), 403);

        $row = Setting::query()->where('key', 'virtualizor.api_url')->first();
        $this->apiUrl = is_array($row?->value) ? (string) ($row->value['url'] ?? '') : (string) ($row?->value ?? '');
        $rowKey = Setting::query()->where('key', 'virtualizor.api_key')->first();
        $this->apiKey = is_array($rowKey?->value) ? (string) ($rowKey->value['key'] ?? '') : (string) ($rowKey?->value ?? '');
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('modules.manage'), 403);

        $this->validate([
            'apiUrl' => 'nullable|url|max:500',
            'apiKey' => 'nullable|string|max:500',
        ]);

        Setting::query()->updateOrCreate(
            ['group' => 'virtualizor', 'key' => 'virtualizor.api_url'],
            ['value' => ['url' => $this->apiUrl], 'is_public' => false],
        );
        Setting::query()->updateOrCreate(
            ['group' => 'virtualizor', 'key' => 'virtualizor.api_key'],
            ['value' => ['key' => $this->apiKey], 'is_public' => false],
        );

        $this->message = 'Configuration Virtualizor enregistrée.';
        $this->dispatch('notify', type: 'success', message: $this->message);
    }

    public function render()
    {
        return view('virtualizor::livewire.virtualizor-index');
    }
}

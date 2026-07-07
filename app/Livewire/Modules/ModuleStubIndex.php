<?php

declare(strict_types=1);

namespace App\Livewire\Modules;

use App\Services\Core\ModuleManager;
use App\Support\ModuleStubRegistry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Module Obiora')]
final class ModuleStubIndex extends Component
{
    public string $slug = '';

    /** @var array<string, mixed> */
    public array $module = [];

    public bool $moduleEnabled = false;

    public function mount(string $slug, ModuleManager $modules): void
    {
        $this->slug = $slug;
        $entry = ModuleStubRegistry::get($slug);

        if ($entry === null) {
            abort(404);
        }

        $this->module = $entry;
        $this->moduleEnabled = $modules->isEnabled($slug);
    }

    public function title(): string
    {
        return (string) ($this->module['name'] ?? 'Module');
    }

    public function render()
    {
        return view('livewire.modules.stub-index');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring Obiora')]
final class MonitoringIndex extends Component
{
    public string $panelUrl = '';

    public function mount(): void
    {
        $this->panelUrl = rtrim((string) config('app.url'), '/');
    }

    public function render()
    {
        return view('monitoring::livewire.monitoring-index');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Services\Monitoring\StatusPageService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Layout('layouts.guest')]
#[Title('Status')]
final class PublicStatusPage extends Component
{
    public function mount(StatusPageService $statusPage): void
    {
        if (! $statusPage->isEnabled()) {
            throw new NotFoundHttpException();
        }
    }

    public function render(StatusPageService $statusPage)
    {
        return view('monitoring::livewire.public-status-page', [
            'status' => $statusPage->payload(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

trait AuthorizesPanelAccess
{
    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}

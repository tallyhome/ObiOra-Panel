<?php

declare(strict_types=1);

namespace Modules\MySQL\Livewire;

use App\Models\ManagedDatabase;
use App\Services\Database\DatabaseManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Base de données')]
final class DatabaseShow extends Component
{
    public ManagedDatabase $database;

    public bool $showPassword = false;

    public function mount(ManagedDatabase $database): void
    {
        $this->database = $database;
    }

    public function togglePassword(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    public function grantDockerAccess(DatabaseManager $databaseManager): void
    {
        try {
            $this->database = $databaseManager->grantDockerAccess($this->database);
            $this->dispatch('notify', type: 'success', message: 'Accès Docker activé pour cette base.');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function delete(DatabaseManager $databaseManager): void
    {
        $name = $this->database->name;

        try {
            $databaseManager->delete($this->database);
            session()->flash('success', "Base « {$name} » supprimée.");
            $this->redirect(route('databases.index'), navigate: true);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function render()
    {
        return view('mysql::livewire.database-show');
    }
}

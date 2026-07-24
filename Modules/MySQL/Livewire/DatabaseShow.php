<?php

declare(strict_types=1);

namespace Modules\MySQL\Livewire;

use App\Models\ManagedDatabase;
use App\Services\Core\ServerManager;
use App\Services\Database\DatabaseManager;
use App\Services\Database\PhpMyAdminService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Base de données')]
final class DatabaseShow extends Component
{
    public ManagedDatabase $database;

    public bool $showPassword = false;

    /** @var array{status: string, port: int, url: ?string, message: string}|null */
    public ?array $phpMyAdmin = null;

    public function mount(ManagedDatabase $database, PhpMyAdminService $phpMyAdmin, ServerManager $serverManager): void
    {
        $this->database = $database;
        $this->phpMyAdmin = $phpMyAdmin->status($serverManager->getCurrentServer());
    }

    public function togglePassword(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    public function openPhpMyAdmin(PhpMyAdminService $phpMyAdmin, ServerManager $serverManager): void
    {
        $result = $phpMyAdmin->ensure($serverManager->getCurrentServer());
        $this->phpMyAdmin = $phpMyAdmin->status($serverManager->getCurrentServer());

        if (! $result['success'] || $result['url'] === null) {
            $this->dispatch('notify', type: 'danger', message: $result['message']);

            return;
        }

        $this->dispatch('open-url', url: $result['url']);
        $this->dispatch('notify', type: 'success', message: $result['message']);
    }

    public function grantDockerAccess(DatabaseManager $databaseManager): void
    {
        try {
            $this->database = $databaseManager->grantDockerAccess($this->database);
            $this->dispatch('notify', type: 'success', message: 'Accès Docker activé pour cette base.');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', type: 'danger', message: 'Erreur interne : '.$e->getMessage());
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

<?php

declare(strict_types=1);

namespace Modules\MySQL\Livewire;

use App\Models\ManagedDatabase;
use App\Services\Core\ServerManager;
use App\Services\Database\DatabaseManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Bases de données')]
final class DatabaseList extends Component
{
    /** @var \Illuminate\Support\Collection<int, ManagedDatabase> */
    public $databases;

    /** @var list<string> */
    public array $serverDatabases = [];

    public string $serverName = '';

    public function mount(DatabaseManager $databaseManager, ServerManager $serverManager): void
    {
        $this->loadDatabases($databaseManager, $serverManager);
    }

    #[On('server-changed')]
    public function onServerChanged(DatabaseManager $databaseManager, ServerManager $serverManager): void
    {
        $this->loadDatabases($databaseManager, $serverManager);
    }

    public function refresh(DatabaseManager $databaseManager, ServerManager $serverManager): void
    {
        $this->loadDatabases($databaseManager, $serverManager);
    }

    public function delete(int $databaseId, DatabaseManager $databaseManager): void
    {
        $database = ManagedDatabase::query()->findOrFail($databaseId);

        try {
            $databaseManager->delete($database);
            session()->flash('success', "Base « {$database->name} » supprimée.");
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }

        $this->loadDatabases($databaseManager, app(ServerManager::class));
    }

    private function loadDatabases(DatabaseManager $databaseManager, ServerManager $serverManager): void
    {
        $server = $serverManager->getCurrentServer();
        $this->serverName = $server?->name ?? 'Aucun';
        $this->databases = $databaseManager->forServer($server);
        $this->serverDatabases = $databaseManager->listOnServer($server);
    }

    public function render()
    {
        return view('mysql::livewire.database-list');
    }
}

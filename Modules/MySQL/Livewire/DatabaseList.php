<?php

declare(strict_types=1);

namespace Modules\MySQL\Livewire;

use App\Models\ManagedDatabase;
use App\Services\Core\ServerManager;
use App\Services\Database\DatabaseManager;
use App\Services\Database\PhpMyAdminService;
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

    /** @var array{status: string, port: int, url: ?string, message: string}|null */
    public ?array $phpMyAdmin = null;

    public function mount(DatabaseManager $databaseManager, ServerManager $serverManager, PhpMyAdminService $phpMyAdmin): void
    {
        $this->loadDatabases($databaseManager, $serverManager);
        $this->phpMyAdmin = $phpMyAdmin->status($serverManager->getCurrentServer());
    }

    #[On('server-changed')]
    public function onServerChanged(DatabaseManager $databaseManager, ServerManager $serverManager, PhpMyAdminService $phpMyAdmin): void
    {
        $this->loadDatabases($databaseManager, $serverManager);
        $this->phpMyAdmin = $phpMyAdmin->status($serverManager->getCurrentServer());
    }

    public function refresh(DatabaseManager $databaseManager, ServerManager $serverManager, PhpMyAdminService $phpMyAdmin): void
    {
        $this->loadDatabases($databaseManager, $serverManager);
        $this->phpMyAdmin = $phpMyAdmin->status($serverManager->getCurrentServer());
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

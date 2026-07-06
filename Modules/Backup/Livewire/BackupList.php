<?php

declare(strict_types=1);

namespace Modules\Backup\Livewire;

use App\Models\Backup;
use App\Services\Backup\BackupManager;
use App\Services\Core\ServerManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sauvegardes')]
final class BackupList extends Component
{
    /** @var \Illuminate\Support\Collection<int, Backup> */
    public $backups;

    public string $serverName = '';

    public function mount(BackupManager $backupManager, ServerManager $serverManager): void
    {
        $this->loadBackups($backupManager, $serverManager);
    }

    #[On('server-changed')]
    public function onServerChanged(BackupManager $backupManager, ServerManager $serverManager): void
    {
        $this->loadBackups($backupManager, $serverManager);
    }

    public function delete(int $backupId, BackupManager $backupManager): void
    {
        $backup = Backup::query()->findOrFail($backupId);

        try {
            $backupManager->delete($backup);
            session()->flash('success', "Sauvegarde « {$backup->name} » supprimée.");
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }

        $this->loadBackups($backupManager, app(ServerManager::class));
    }

    private function loadBackups(BackupManager $backupManager, ServerManager $serverManager): void
    {
        $server = $serverManager->getCurrentServer();
        $this->serverName = $server?->name ?? 'Aucun';
        $this->backups = $backupManager->forServer($server);
    }

    public function render()
    {
        return view('backup::livewire.backup-list');
    }
}

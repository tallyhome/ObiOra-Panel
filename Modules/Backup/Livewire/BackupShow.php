<?php

declare(strict_types=1);

namespace Modules\Backup\Livewire;

use App\Enums\BackupType;
use App\Models\Backup;
use App\Services\Backup\BackupManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Détail sauvegarde')]
final class BackupShow extends Component
{
    public Backup $backup;

    public string $restore_database = '';

    public function mount(Backup $backup): void
    {
        $this->backup = $backup;
        $this->restore_database = $backup->target ?? '';
    }

    public function restore(BackupManager $backupManager): void
    {
        if ($this->backup->type !== BackupType::Database) {
            $this->dispatch('notify', type: 'danger', message: 'Restauration disponible uniquement pour les dumps SQL.');

            return;
        }

        $this->validate([
            'restore_database' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]*$/'],
        ]);

        try {
            $db = $backupManager->restore(
                $this->backup,
                $this->restore_database !== '' ? $this->restore_database : null,
            );
            session()->flash('success', "Base restaurée : {$db}");
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function delete(BackupManager $backupManager): void
    {
        $name = $this->backup->name;

        try {
            $backupManager->delete($this->backup);
            session()->flash('success', "Sauvegarde « {$name} » supprimée.");
            $this->redirect(route('backups.index'), navigate: true);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function render()
    {
        return view('backup::livewire.backup-show');
    }
}

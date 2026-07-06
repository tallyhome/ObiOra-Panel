<?php

declare(strict_types=1);

namespace Modules\Backup\Livewire;

use App\Services\Backup\BackupManager;
use App\Services\Core\ServerManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Créer une sauvegarde')]
final class BackupCreate extends Component
{
    public string $name = '';

    public string $type = 'database';

    public string $target = '';

    public function save(BackupManager $backupManager, ServerManager $serverManager): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'type' => ['required', 'in:database,files,full'],
        ];

        if ($this->type === 'database') {
            $rules['target'] = ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]*$/'];
        }

        if ($this->type === 'files') {
            $rules['target'] = ['nullable', 'string', 'max:255'];
        }

        $this->validate($rules);

        try {
            $backup = $backupManager->create([
                'name' => $this->name,
                'type' => $this->type,
                'target' => $this->target !== '' ? $this->target : ($this->type === 'database' ? 'all' : null),
            ], $serverManager->getCurrentServer());
        } catch (\InvalidArgumentException $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        session()->flash('success', "Sauvegarde « {$backup->name} » créée ({$backup->humanSize()}).");

        $this->redirect(route('backups.show', $backup), navigate: true);
    }

    public function render()
    {
        return view('backup::livewire.backup-create');
    }
}

<?php

declare(strict_types=1);

namespace Modules\MySQL\Livewire;

use App\Services\Core\ServerManager;
use App\Services\Database\DatabaseManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Créer une base de données')]
final class DatabaseCreate extends Component
{
    public string $name = '';

    public string $username = '';

    public string $password = '';

    public bool $auto_password = true;

    public function save(DatabaseManager $databaseManager, ServerManager $serverManager): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
            'username' => ['nullable', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_]*$/'],
            'auto_password' => ['boolean'],
        ];

        if (! $this->auto_password) {
            $rules['password'] = ['required', 'string', 'min:12', 'max:64'];
        }

        $this->validate($rules);

        try {
            $database = $databaseManager->create([
                'name' => strtolower($this->name),
                'username' => $this->username !== '' ? $this->username : null,
                'password' => $this->auto_password ? null : $this->password,
            ], $serverManager->getCurrentServer());
        } catch (\InvalidArgumentException $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        session()->flash('success', "Base « {$database->name} » créée.");

        $this->redirect(route('databases.show', $database), navigate: true);
    }

    public function render()
    {
        return view('mysql::livewire.database-create');
    }
}

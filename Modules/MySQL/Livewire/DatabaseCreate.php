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
    /** Étape wizard style cPanel : 1=base, 2=utilisateur, 3=confirmation */
    public int $step = 1;

    public string $name = '';

    public string $username = '';

    public string $password = '';

    public bool $auto_password = true;

    public bool $create_user = true;

    public function updatedName(string $value): void
    {
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $value) ?? '');
        $this->name = $clean;

        if ($this->create_user && ($this->username === '' || $this->usernameLooksAuto())) {
            $this->username = $this->suggestedUsername($clean);
        }
    }

    public function updatedCreateUser(bool $value): void
    {
        if ($value && $this->name !== '' && $this->username === '') {
            $this->username = $this->suggestedUsername($this->name);
        }
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
            ]);

            if ($this->create_user && $this->username === '') {
                $this->username = $this->suggestedUsername($this->name);
            }

            $this->step = 2;

            return;
        }

        if ($this->step === 2) {
            $rules = [
                'create_user' => ['boolean'],
                'auto_password' => ['boolean'],
            ];

            if ($this->create_user) {
                $rules['username'] = ['required', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_]+$/'];
                if (! $this->auto_password) {
                    $rules['password'] = ['required', 'string', 'min:12', 'max:64'];
                }
            }

            $this->validate($rules);
            $this->step = 3;
        }
    }

    public function previousStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step < $this->step) {
            $this->step = $step;
        }
    }

    public function save(DatabaseManager $databaseManager, ServerManager $serverManager): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
            'create_user' => ['boolean'],
            'auto_password' => ['boolean'],
        ];

        if ($this->create_user) {
            $rules['username'] = ['required', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_]+$/'];
            if (! $this->auto_password) {
                $rules['password'] = ['required', 'string', 'min:12', 'max:64'];
            }
        }

        $this->validate($rules);
        $this->step = 3;

        try {
            $database = $databaseManager->create([
                'name' => strtolower($this->name),
                'username' => $this->create_user ? $this->username : null,
                'password' => ($this->create_user && ! $this->auto_password) ? $this->password : null,
            ], $serverManager->getCurrentServer());
        } catch (\InvalidArgumentException $e) {
            $this->addError('name', $e->getMessage());
            $this->step = 1;

            return;
        }

        session()->flash('success', "Base « {$database->name} » créée.");

        $this->redirect(route('databases.show', $database), navigate: true);
    }

    public function render()
    {
        return view('mysql::livewire.database-create', [
            'previewUsername' => $this->create_user
                ? ($this->username !== '' ? $this->username : $this->suggestedUsername($this->name))
                : '—',
        ]);
    }

    private function suggestedUsername(string $dbName): string
    {
        if ($dbName === '') {
            return '';
        }

        $base = substr($dbName, 0, 27);

        return $base.'_user';
    }

    private function usernameLooksAuto(): bool
    {
        if ($this->username === '') {
            return true;
        }

        return str_ends_with($this->username, '_user');
    }
}

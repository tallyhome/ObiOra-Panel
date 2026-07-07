<?php

declare(strict_types=1);

namespace Modules\Users\Livewire;

use App\Models\User;
use App\Services\Infrastructure\PanelUserManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Utilisateurs')]
final class UserIndex extends Component
{
    /** @var \Illuminate\Database\Eloquent\Collection<int, User> */
    public $users;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'client';

    public bool $is_active = true;

    /** @var list<string> */
    public array $roles = [];

    public function mount(PanelUserManager $users): void
    {
        abort_unless(auth()->user()?->can('users.view'), 403);
        $this->roles = $users->availableRoles();
        $this->loadUsers($users);
    }

    public function create(PanelUserManager $users): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $users->create($validated);
        $this->resetForm();
        $this->loadUsers($users);
        $this->dispatch('notify', type: 'success', message: 'Utilisateur créé.');
    }

    public function edit(int $userId): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);

        $user = User::query()->findOrFail($userId);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->roles->first()?->name ?? 'client';
        $this->is_active = (bool) $user->is_active;
        $this->showForm = true;
    }

    public function update(PanelUserManager $users): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);

        $user = User::query()->findOrFail($this->editingId);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'role' => 'required|string',
            'is_active' => 'boolean',
        ];

        if ($this->password !== '') {
            $rules['password'] = 'string|min:8';
        }

        $validated = $this->validate($rules);

        if ($this->password === '') {
            unset($validated['password']);
        }

        $users->update($user, $validated);
        $this->resetForm();
        $this->loadUsers($users);
        $this->dispatch('notify', type: 'success', message: 'Utilisateur mis à jour.');
    }

    public function delete(int $userId, PanelUserManager $users): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);

        $users->delete(User::query()->findOrFail($userId));
        $this->loadUsers($users);
        $this->dispatch('notify', type: 'success', message: 'Utilisateur supprimé.');
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->role = 'client';
        $this->is_active = true;
    }

    private function loadUsers(PanelUserManager $users): void
    {
        $this->users = $users->list();
    }

    public function render()
    {
        return view('users::livewire.user-index');
    }
}

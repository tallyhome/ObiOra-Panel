<?php

declare(strict_types=1);

namespace Modules\Users\Livewire;

use App\Services\Infrastructure\PanelUserManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mon profil')]
final class ProfileIndex extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function save(PanelUserManager $users): void
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
        ];

        if ($this->password !== '') {
            $rules['password'] = ['required', 'confirmed', Password::min(8)];
        }

        $validated = $this->validate($rules);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if ($this->password !== '') {
            $payload['password'] = $this->password;
        }

        $users->update($user, $payload);

        $this->password = '';
        $this->password_confirmation = '';

        $this->dispatch('notify', type: 'success', message: 'Profil mis à jour.');
    }

    public function render()
    {
        $user = Auth::user();

        return view('users::livewire.profile-index', [
            'roleLabel' => $user?->roles->first()?->name ?? '—',
            'lastLogin' => $user?->last_login_at?->format('d/m/Y H:i'),
        ]);
    }
}

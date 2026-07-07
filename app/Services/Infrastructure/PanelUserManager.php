<?php

declare(strict_types=1);

namespace App\Services\Infrastructure;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class PanelUserManager
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function list()
    {
        return User::query()->with('roles')->orderBy('name')->get();
    }

    /**
     * @return list<string>
     */
    public function availableRoles(): array
    {
        return Role::query()->orderBy('name')->pluck('name')->all();
    }

    /**
     * @param  array{name: string, email: string, password: string, role: string, is_active?: bool}  $data
     */
    public function create(array $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->syncRoles([$data['role']]);

        return $user;
    }

    /**
     * @param  array{name?: string, email?: string, password?: string, role?: string, is_active?: bool}  $data
     */
    public function update(User $user, array $data): User
    {
        if (isset($data['role'])) {
            $this->guardNotLastSuperAdminRemoval($user, $data['role']);
            $user->syncRoles([$data['role']]);
        }

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }

        if (isset($data['email'])) {
            $user->email = $data['email'];
        }

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (array_key_exists('is_active', $data)) {
            if (! $data['is_active'] && auth()->id() === $user->id) {
                throw ValidationException::withMessages(['is_active' => 'Vous ne pouvez pas désactiver votre propre compte.']);
            }
            $user->is_active = (bool) $data['is_active'];
        }

        $user->save();

        return $user->fresh(['roles']);
    }

    public function delete(User $user): void
    {
        if (auth()->id() === $user->id) {
            throw ValidationException::withMessages(['user' => 'Impossible de supprimer votre propre compte.']);
        }

        if ($user->hasRole('super-admin') && User::role('super-admin')->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'Impossible de supprimer le dernier super-admin.']);
        }

        $user->delete();
    }

    private function guardNotLastSuperAdminRemoval(?User $user, string $newRole): void
    {
        if ($user === null || ! $user->hasRole('super-admin')) {
            return;
        }

        if ($newRole !== 'super-admin' && User::role('super-admin')->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'Impossible de retirer le rôle super-admin du dernier compte.']);
        }
    }
}

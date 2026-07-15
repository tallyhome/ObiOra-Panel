<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Models\User;
use App\Support\PanelPermissions;
use App\Support\PanelTimezone;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class DemoAccountService
{
    public function create(string $email, string $name, int $ttlHours): array
    {
        $this->ensureClientRole();

        if (User::query()->where('email', $email)->exists()) {
            $email = 'demo-'.Str::lower(Str::random(8)).'@demo.obiora.io';
        }

        $password = Str::password(14);

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'timezone' => config('obiora.default_timezone', 'Europe/Paris'),
            'is_active' => true,
            'is_demo' => true,
            'email_verified_at' => now(),
            'demo_expires_at' => PanelTimezone::now()->addHours($ttlHours),
        ]);

        $user->syncRoles(['client']);

        return [
            'user_id' => $user->id,
            'email' => $email,
            'password' => $password,
            'expires_at' => $user->demo_expires_at?->toIso8601String(),
            'login_url' => $this->signedLoginUrl($user, $ttlHours),
        ];
    }

    public function delete(int $userId): bool
    {
        $user = User::query()->where('id', $userId)->where('is_demo', true)->first();

        if ($user === null) {
            return false;
        }

        $user->delete();

        return true;
    }

    public function expireDue(): int
    {
        $users = User::query()
            ->where('is_demo', true)
            ->whereNotNull('demo_expires_at')
            ->where('demo_expires_at', '<', now())
            ->get();

        foreach ($users as $user) {
            $user->delete();
        }

        return $users->count();
    }

    private function ensureClientRole(): void
    {
        foreach (PanelPermissions::ALL as $permission) {
            Permission::findOrCreate($permission);
        }

        $role = Role::findOrCreate('client');
        $role->syncPermissions(PanelPermissions::forRole('client'));
    }

    private function signedLoginUrl(User $user, int $ttlHours): string
    {
        URL::forceRootUrl(config('obiora.public_url'));

        return URL::temporarySignedRoute(
            'demo.enter',
            now()->addHours($ttlHours),
            ['user' => $user->id],
        );
    }
}

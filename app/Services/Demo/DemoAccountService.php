<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DemoAccountService
{
    public function create(string $email, string $name, int $ttlHours): array
    {
        if (User::query()->where('email', $email)->exists()) {
            $email = 'demo-'.Str::lower(Str::random(8)).'@demo.obiora.io';
        }

        $password = Str::password(14);

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
            'is_demo' => true,
            'email_verified_at' => now(),
            'demo_expires_at' => now()->addHours($ttlHours),
        ]);

        $user->syncRoles(['client']);

        return [
            'user_id' => $user->id,
            'email' => $email,
            'password' => $password,
            'expires_at' => $user->demo_expires_at?->toIso8601String(),
            'login_url' => url('/login'),
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
}

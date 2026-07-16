<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Connexion')]
final class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login.'.$this->email;

        try {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw ValidationException::withMessages([
                    'email' => __('panel.auth.too_many', ['seconds' => RateLimiter::availableIn($key)]),
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable) {
            // Cache/Redis KO : ne pas bloquer le login derrière un 500 opaque.
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            try {
                RateLimiter::hit($key, 60);
            } catch (\Throwable) {
                // ignore
            }
            throw ValidationException::withMessages([
                'email' => __('panel.auth.invalid_credentials'),
            ]);
        }

        $user = Auth::user();
        if ($user !== null && $user->isDemoExpired()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => __('panel.auth.demo_expired'),
            ]);
        }

        try {
            RateLimiter::clear($key);
        } catch (\Throwable) {
            // ignore
        }
        session()->regenerate();

        $this->redirectIntended(default: route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}

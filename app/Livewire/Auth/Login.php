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

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('panel.auth.too_many', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            RateLimiter::hit($key, 60);
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

        RateLimiter::clear($key);
        session()->regenerate();

        $this->redirectIntended(default: route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}

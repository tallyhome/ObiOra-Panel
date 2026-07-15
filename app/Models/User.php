<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PanelTimezone;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'is_active',
        'is_demo',
        'demo_expires_at',
        'last_login_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
            'demo_expires_at' => 'datetime',
        ];
    }

    public function isDemoExpired(): bool
    {
        return $this->is_demo
            && $this->demo_expires_at !== null
            && $this->demo_expires_at->isPast();
    }

    public function demoExpiresInHours(): ?int
    {
        $minutes = $this->demoExpiresInMinutes();

        return $minutes === null ? null : (int) ceil($minutes / 60);
    }

    public function demoExpiresInMinutes(): ?int
    {
        if (! $this->is_demo || $this->demo_expires_at === null) {
            return null;
        }

        return (int) max(0, now()->diffInMinutes($this->demo_expires_at, false));
    }

    public function demoExpiresAtLabel(): string
    {
        if ($this->demo_expires_at === null) {
            return '—';
        }

        return PanelTimezone::format($this->demo_expires_at, 'd/m/Y à H:i')
            .' ('.PanelTimezone::label().')';
    }

    public function demoRemainingLabel(): string
    {
        $minutes = $this->demoExpiresInMinutes();

        if ($minutes === null) {
            return '';
        }

        if ($minutes >= 60) {
            $hours = intdiv($minutes, 60);
            $rest = $minutes % 60;

            return $rest > 0 ? "{$hours} h {$rest} min" : "{$hours} h";
        }

        return "{$minutes} min";
    }
}

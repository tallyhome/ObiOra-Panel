<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MonitorType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Monitor extends Model
{
    /** @use HasFactory<\Database\Factories\MonitorFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'target',
        'port',
        'keyword',
        'keyword_present',
        'interval_seconds',
        'tags',
        'is_active',
        'last_status',
        'last_checked_at',
        'last_response_ms',
        'track_token',
    ];

    protected function casts(): array
    {
        return [
            'type' => MonitorType::class,
            'keyword_present' => 'boolean',
            'is_active' => 'boolean',
            'tags' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }

    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Monitor $monitor): void {
            if ($monitor->track_token === null && in_array($monitor->type, [
                MonitorType::Https,
                MonitorType::Http,
                MonitorType::Keyword,
            ], true)) {
                $monitor->track_token = (string) Str::uuid();
            }
        });
    }

    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->last_checked_at === null) {
            return true;
        }

        return $this->last_checked_at->diffInSeconds(now()) >= $this->interval_seconds;
    }

    public function displayTarget(): string
    {
        if ($this->type === MonitorType::Port && $this->port !== null) {
            return $this->target.':'.$this->port;
        }

        return $this->target;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceWindow extends Model
{
    protected $fillable = [
        'resource_type',
        'resource_ids',
        'starts_at',
        'ends_at',
        'note',
        'created_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'resource_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(?\DateTimeInterface $at = null): bool
    {
        if ($this->cancelled_at !== null) {
            return false;
        }

        $moment = $at !== null ? \Illuminate\Support\Carbon::parse($at) : now();

        return $this->starts_at->lte($moment) && $this->ends_at->gte($moment);
    }

    public function isScheduled(): bool
    {
        return $this->cancelled_at === null && $this->starts_at->isFuture();
    }

    public function scopeActive($query, ?\DateTimeInterface $at = null)
    {
        $moment = $at !== null ? \Illuminate\Support\Carbon::parse($at) : now();

        return $query
            ->whereNull('cancelled_at')
            ->where('starts_at', '<=', $moment)
            ->where('ends_at', '>=', $moment);
    }
}

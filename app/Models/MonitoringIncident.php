<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoringIncident extends Model
{
    protected $fillable = [
        'resource_type',
        'resource_id',
        'resource_name',
        'trigger',
        'message',
        'alert_policy_id',
        'went_down_at',
        'recovered_at',
        'status',
        'metadata',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'went_down_at' => 'datetime',
            'recovered_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(AlertPolicy::class, 'alert_policy_id');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}

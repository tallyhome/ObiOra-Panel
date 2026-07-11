<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CrashHunterEvent extends Model
{
    protected $fillable = [
        'server_id',
        'event_type',
        'severity',
        'title',
        'details',
        'payload',
        'detected_at',
        'notified',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'detected_at' => 'datetime',
            'notified' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

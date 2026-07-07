<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringAlert extends Model
{
    protected $fillable = [
        'server_id',
        'type',
        'severity',
        'title',
        'message',
        'payload',
        'notified',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'notified' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

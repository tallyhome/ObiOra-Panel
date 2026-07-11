<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CrashHunterIncident extends Model
{
    protected $fillable = [
        'server_id',
        'external_id',
        'triggers',
        'snapshot_count',
        'started_at',
        'ended_at',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'triggers' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

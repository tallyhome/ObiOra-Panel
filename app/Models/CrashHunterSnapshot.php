<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CrashHunterSnapshot extends Model
{
    protected $fillable = [
        'server_id',
        'slot',
        'sampled_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

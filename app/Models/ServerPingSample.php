<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerPingSample extends Model
{
    protected $fillable = [
        'server_id',
        'latency_ms',
        'success',
        'method',
        'sampled_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'sampled_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

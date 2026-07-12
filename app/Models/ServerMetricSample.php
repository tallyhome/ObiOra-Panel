<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerMetricSample extends Model
{
    protected $fillable = [
        'server_id',
        'sampled_at',
        'cpu_percent',
        'cpu_steal_percent',
        'memory_percent',
        'swap_percent',
        'disk_percent',
        'load_1',
        'load_5',
        'load_15',
        'uptime_seconds',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'datetime',
            'cpu_percent' => 'float',
            'cpu_steal_percent' => 'float',
            'memory_percent' => 'float',
            'swap_percent' => 'float',
            'disk_percent' => 'float',
            'load_1' => 'float',
            'load_5' => 'float',
            'load_15' => 'float',
            'payload' => 'array',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

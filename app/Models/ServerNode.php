<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerNode extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'connection_type',
        'host',
        'port',
        'username',
        'is_primary',
        'is_active',
        'credentials',
        'last_ping_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'credentials' => 'encrypted:array',
            'last_ping_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

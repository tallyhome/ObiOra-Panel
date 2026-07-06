<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServerStatus;
use App\Enums\ServerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hostname',
        'ip_address',
        'type',
        'status',
        'is_master',
        'os_name',
        'os_version',
        'agent_token',
        'last_seen_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => ServerType::class,
            'status' => ServerStatus::class,
            'is_master' => 'boolean',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(ServerNode::class);
    }

    public function primaryNode(): HasOne
    {
        return $this->hasOne(ServerNode::class)->where('is_primary', true);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function managedDatabases(): HasMany
    {
        return $this->hasMany(ManagedDatabase::class);
    }
}

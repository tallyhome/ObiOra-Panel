<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'installation_uuid',
        'license_key',
        'plan',
        'status',
        'activated_at',
        'expires_at',
        'limits',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'limits' => 'array',
            'metadata' => 'array',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstalledApplication extends Model
{
    protected $fillable = [
        'server_id',
        'slug',
        'name',
        'version',
        'category',
        'status',
        'installed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'installed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

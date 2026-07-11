<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CrashHunterReport extends Model
{
    protected $fillable = [
        'server_id',
        'external_id',
        'hostname',
        'trigger_type',
        'generated_at',
        'report_json',
        'bundle_path',
    ];

    protected function casts(): array
    {
        return [
            'report_json' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

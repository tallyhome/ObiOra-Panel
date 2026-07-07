<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticReport extends Model
{
    protected $fillable = [
        'server_id',
        'external_id',
        'schema_version',
        'doctor_version',
        'score',
        'status',
        'hostname',
        'generated_at',
        'received_at',
        'report_json',
        'critical_findings',
        'support_mode',
        'signature',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'received_at' => 'datetime',
            'report_json' => 'array',
            'critical_findings' => 'array',
            'support_mode' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

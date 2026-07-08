<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrashAnalyzerReport extends Model
{
    protected $fillable = [
        'server_id',
        'external_id',
        'hostname',
        'trigger_type',
        'generated_at',
        'report_json',
        'pdf_path',
        'html_path',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'report_json' => 'array',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

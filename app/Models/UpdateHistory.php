<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpdateHistory extends Model
{
    use HasFactory;

    protected $table = 'update_history';

    protected $fillable = [
        'from_version',
        'to_version',
        'status',
        'progress',
        'progress_message',
        'changelog_url',
        'backup_path',
        'output',
        'rolled_back',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'rolled_back' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusPageSetting extends Model
{
    protected $fillable = [
        'is_enabled',
        'title',
        'slug',
        'noindex',
        'visible_server_ids',
        'visible_monitor_ids',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'noindex' => 'boolean',
            'visible_server_ids' => 'array',
            'visible_monitor_ids' => 'array',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['id' => 1],
            [
                'is_enabled' => true,
                'title' => 'ObiOra Status',
                'slug' => 'status',
                'noindex' => true,
            ],
        );
    }
}

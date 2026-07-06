<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebsiteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Website extends Model
{
    protected $fillable = [
        'server_id',
        'domain',
        'document_root',
        'php_version',
        'ssl_enabled',
        'ssl_expires_at',
        'ssl_email',
        'status',
        'nginx_config_path',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => WebsiteStatus::class,
            'ssl_enabled' => 'boolean',
            'ssl_expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DatabaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ManagedDatabase extends Model
{
    protected $table = 'managed_databases';

    protected $fillable = [
        'server_id',
        'name',
        'username',
        'password',
        'host',
        'charset',
        'collation',
        'status',
        'metadata',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'status' => DatabaseStatus::class,
            'metadata' => 'array',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    public function getPasswordPlainAttribute(): string
    {
        return Crypt::decryptString($this->attributes['password']);
    }
}

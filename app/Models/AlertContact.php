<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertContact extends Model
{
    protected $fillable = [
        'name',
        'email',
        'slack_webhook',
        'discord_webhook',
        'telegram_bot_token',
        'telegram_chat_id',
        'webhook_url',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    /** @return list<string> */
    public function availableChannels(): array
    {
        $channels = [];

        if ($this->email) {
            $channels[] = 'email';
        }
        if ($this->slack_webhook) {
            $channels[] = 'slack';
        }
        if ($this->discord_webhook) {
            $channels[] = 'discord';
        }
        if ($this->telegram_bot_token && $this->telegram_chat_id) {
            $channels[] = 'telegram';
        }
        if ($this->webhook_url) {
            $channels[] = 'webhook';
        }

        return $channels;
    }
}

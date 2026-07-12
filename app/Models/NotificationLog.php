<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'monitoring_incident_id',
        'alert_contact_id',
        'channel',
        'status',
        'response',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(MonitoringIncident::class, 'monitoring_incident_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(AlertContact::class, 'alert_contact_id');
    }
}

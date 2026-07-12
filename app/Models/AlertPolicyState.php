<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertPolicyState extends Model
{
    protected $fillable = [
        'alert_policy_id',
        'resource_type',
        'resource_id',
        'condition_met_since',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'condition_met_since' => 'datetime',
            'last_notified_at' => 'datetime',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(AlertPolicy::class, 'alert_policy_id');
    }
}

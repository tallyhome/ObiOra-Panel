<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorVisitDaily extends Model
{
    protected $table = 'monitor_visit_daily';

    protected $fillable = [
        'monitor_id',
        'visit_date',
        'visits',
        'unique_visitors',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'visits' => 'integer',
            'unique_visitors' => 'integer',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}

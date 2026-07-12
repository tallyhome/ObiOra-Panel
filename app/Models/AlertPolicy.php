<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertPolicyOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertPolicy extends Model
{
    protected $fillable = [
        'name',
        'metric',
        'operator',
        'value',
        'value_unit',
        'duration_minutes',
        'repeat_minutes',
        'apply_to',
        'apply_target_ids',
        'notify_contact_ids',
        'description',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'operator' => AlertPolicyOperator::class,
            'value' => 'float',
            'duration_minutes' => 'integer',
            'repeat_minutes' => 'integer',
            'apply_target_ids' => 'array',
            'notify_contact_ids' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(MonitoringIncident::class);
    }

    public function conditionLabel(): string
    {
        $op = $this->operator instanceof AlertPolicyOperator
            ? $this->operator->label()
            : (string) $this->operator;

        return sprintf(
            '%s %s %s%s',
            $this->metricLabel(),
            $op,
            $this->value,
            $this->value_unit ?? '',
        );
    }

    public function metricLabel(): string
    {
        return match ($this->metric) {
            'cpu_usage_percent' => 'CPU',
            'cpu_steal_percent' => 'CPU Steal',
            'memory_usage_percent' => 'Memory',
            'disk_usage_percent' => 'Disk',
            'load_per_core' => 'Load/Core',
            'uptime_seconds' => 'Uptime',
            'agent_no_data_minutes' => 'No data',
            'monitor_status' => 'Monitor status',
            'ssl_expiry_days' => 'SSL expiry',
            default => $this->metric,
        };
    }
}

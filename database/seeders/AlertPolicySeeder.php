<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AlertPolicyOperator;
use App\Models\AlertContact;
use App\Models\AlertPolicy;
use Illuminate\Database\Seeder;

final class AlertPolicySeeder extends Seeder
{
    public function run(): void
    {
        $contact = AlertContact::query()->firstOrCreate(
            ['name' => 'Default Contact'],
            [
                'email' => config('mail.from.address'),
                'is_default' => true,
            ],
        );

        if (! $contact->is_default) {
            $contact->forceFill(['is_default' => true])->save();
        }

        $contactIds = [$contact->id];

        $policies = [
            ['High CPU Steal', 'cpu_steal_percent', AlertPolicyOperator::Gt, 10, '%', 5, 60, 'all', 'CPU steal > 10% for 5 min (VM noisy neighbor).'],
            ['High CPU Usage', 'cpu_usage_percent', AlertPolicyOperator::Gt, 90, '%', 10, 60, 'all', 'CPU usage above 90% for 10 minutes.'],
            ['High Disk Usage', 'disk_usage_percent', AlertPolicyOperator::Gt, 90, '%', 15, 60, 'all', 'Max partition disk usage above 90% for 15 minutes.'],
            ['High Load Average', 'load_per_core', AlertPolicyOperator::Gt, 2, '', 10, 60, 'all', 'Load average per CPU core above 2 for 10 minutes.'],
            ['High Memory Usage', 'memory_usage_percent', AlertPolicyOperator::Gt, 90, '%', 10, 60, 'all', 'Memory usage above 90% for 10 minutes.'],
            ['Monitor Down', 'monitor_status', AlertPolicyOperator::Eq, 0, '', 0, 60, 'monitors', 'External monitor is down (immediate).'],
            ['No Data Received', 'agent_no_data_minutes', AlertPolicyOperator::Gt, 15, ' min', 0, 60, 'servers', 'No agent metrics received for 15 minutes.'],
            ['Server Rebooted', 'uptime_seconds', AlertPolicyOperator::Lt, 300, ' s', 0, 60, 'servers', 'Server uptime under 5 minutes (recent reboot).'],
            ['SSL Certificate Expiring', 'ssl_expiry_days', AlertPolicyOperator::Lt, 14, ' days', 0, 1440, 'monitors', 'HTTPS monitor SSL certificate expires within 14 days.'],
        ];

        foreach ($policies as [$name, $metric, $operator, $value, $unit, $duration, $repeat, $applyTo, $desc]) {
            AlertPolicy::query()->updateOrCreate(
                ['name' => $name],
                [
                    'metric' => $metric,
                    'operator' => $operator,
                    'value' => $value,
                    'value_unit' => $unit,
                    'duration_minutes' => $duration,
                    'repeat_minutes' => $repeat,
                    'apply_to' => $applyTo,
                    'apply_target_ids' => null,
                    'notify_contact_ids' => $contactIds,
                    'description' => $desc,
                    'is_enabled' => true,
                ],
            );
        }
    }
}

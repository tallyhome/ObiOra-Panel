<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Probes;

use App\Enums\MonitorType;
use App\Models\Monitor;

final class MonitorProbeFactory
{
    public function for(Monitor $monitor): MonitorProbe
    {
        return match ($monitor->type) {
            MonitorType::Https => new HttpMonitorProbe(verifySsl: true),
            MonitorType::Http => new HttpMonitorProbe(verifySsl: false),
            MonitorType::Ping => new PingMonitorProbe,
            MonitorType::Port => new PortMonitorProbe,
            MonitorType::Keyword => new KeywordMonitorProbe,
            MonitorType::Dns => new DnsMonitorProbe,
        };
    }
}

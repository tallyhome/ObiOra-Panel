<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Probes;

use App\Models\Monitor;

interface MonitorProbe
{
    public function check(Monitor $monitor): MonitorCheckResult;
}

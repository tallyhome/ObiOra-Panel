<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\Monitor;
use App\Models\MonitorVisitDaily;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class MonitorVisitService
{
    public function recordHit(string $trackToken, ?string $ip = null): bool
    {
        $monitor = Monitor::query()->where('track_token', $trackToken)->where('is_active', true)->first();

        if ($monitor === null) {
            return false;
        }

        $today = now()->toDateString();
        $row = MonitorVisitDaily::query()->firstOrCreate(
            ['monitor_id' => $monitor->id, 'visit_date' => $today],
            ['visits' => 0, 'unique_visitors' => 0],
        );

        $row->increment('visits');

        $ipHash = $ip ? hash('sha256', $ip) : null;

        if ($ipHash !== null) {
            $cacheKey = "monitor_visit:{$monitor->id}:{$today}:{$ipHash}";

            if (Cache::add($cacheKey, 1, now()->addDay())) {
                $row->increment('unique_visitors');
            }
        }

        return true;
    }

    /**
     * @return array{today: int, unique_today: int, total_30d: int, unique_30d: int}
     */
    public function statsForMonitor(Monitor $monitor, int $days = 30): array
    {
        $since = now()->subDays($days)->toDateString();
        $today = now()->toDateString();

        $rows = MonitorVisitDaily::query()
            ->where('monitor_id', $monitor->id)
            ->where('visit_date', '>=', $since)
            ->get();

        $todayRow = $rows->firstWhere('visit_date', $today);

        return [
            'today' => (int) ($todayRow?->visits ?? 0),
            'unique_today' => (int) ($todayRow?->unique_visitors ?? 0),
            'total_30d' => (int) $rows->sum('visits'),
            'unique_30d' => (int) $rows->sum('unique_visitors'),
        ];
    }

    public function embedSnippet(Monitor $monitor): ?string
    {
        if ($monitor->track_token === null) {
            return null;
        }

        $url = route('monitoring.track.pixel', ['token' => $monitor->track_token]);

        return sprintf(
            '<script async src="%s"></script>',
            route('monitoring.track.script', ['token' => $monitor->track_token]),
        );
    }

    public function pixelUrl(Monitor $monitor): ?string
    {
        if ($monitor->track_token === null) {
            return null;
        }

        return route('monitoring.track.pixel', ['token' => $monitor->track_token]);
    }

    public function ensureTrackToken(Monitor $monitor): Monitor
    {
        if ($monitor->track_token !== null) {
            return $monitor;
        }

        $monitor->forceFill(['track_token' => (string) Str::uuid()])->save();

        return $monitor;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Jobs\MonitorCheckJob;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Support\UserTimezone;
use App\Services\Monitoring\Probes\MonitorProbeFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class MonitorRunnerService
{
    public function __construct(
        private readonly MonitorProbeFactory $probes,
        private readonly MonitoringPeriodResolver $periods,
    ) {}

    public function dispatchDueChecks(): int
    {
        $count = 0;

        Monitor::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(50, function ($monitors) use (&$count): void {
                foreach ($monitors as $monitor) {
                    if (! $monitor->isDue()) {
                        continue;
                    }

                    MonitorCheckJob::dispatch($monitor->id);
                    $count++;
                }
            });

        return $count;
    }

    public function runCheck(int $monitorId): MonitorCheck
    {
        $monitor = Monitor::query()->findOrFail($monitorId);

        $probe = $this->probes->for($monitor);
        $result = $probe->check($monitor);
        $checkedAt = now();

        $metrics = $result->metrics;

        if ($result->error !== null) {
            $metrics['error'] = $result->error;
        }

        $check = MonitorCheck::query()->create([
            'monitor_id' => $monitor->id,
            'status' => $result->status,
            'response_ms' => $result->responseMs,
            'metrics' => $metrics,
            'checked_at' => $checkedAt,
        ]);

        $monitor->forceFill([
            'last_status' => $result->status,
            'last_checked_at' => $checkedAt,
            'last_response_ms' => $result->responseMs,
        ])->save();

        if (! $result->isUp() && $result->error !== null) {
            Log::info('Monitor check down', [
                'monitor_id' => $monitor->id,
                'error' => $result->error,
            ]);
        }

        return $check;
    }

    /**
     * @return array{from: Carbon, to: Carbon, label: string, preset: string}
     */
    public function resolvePreset(string $preset): array
    {
        $range = $this->periods->resolve($preset);

        return [
            'from' => $range['from'],
            'to' => $range['to'],
            'label' => $range['label'],
            'preset' => $range['preset'],
        ];
    }

    /**
     * @return array{categories: list<string>, values: list<int|null>, status_segments: list<array{at: string, status: string}>}
     */
    public function chartSeriesForMonitor(Monitor $monitor, int $days = 30): array
    {
        $since = now()->subDays($days);

        return $this->chartSeriesForPeriod($monitor, $since, now());
    }

    /**
     * @return array{categories: list<string>, values: list<int|null>, status_segments: list<array{at: string, status: string}>}
     */
    public function chartSeriesForPeriod(Monitor $monitor, Carbon $from, Carbon $to): array
    {
        $checks = MonitorCheck::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $from)
            ->where('checked_at', '<=', $to)
            ->orderBy('checked_at')
            ->get();

        $categories = [];
        $values = [];
        $segments = [];

        foreach ($checks as $check) {
            $label = UserTimezone::format($check->checked_at, 'd/m H:i');
            $categories[] = $label;
            $values[] = $check->response_ms;
            $segments[] = [
                'at' => $label,
                'status' => $check->status,
            ];
        }

        return [
            'categories' => $categories,
            'values' => $values,
            'status_segments' => $segments,
        ];
    }

    /**
     * @return list<array{status: string, color: string, title: string}>
     */
    public function statusTimelineForPeriod(Monitor $monitor, Carbon $from, Carbon $to, ?string $preset = null): array
    {
        $checks = MonitorCheck::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $from)
            ->where('checked_at', '<=', $to)
            ->orderBy('checked_at')
            ->get();

        $slots = $this->periods->timelineSlots($preset ?? '24h');

        if ($checks->isEmpty()) {
            return array_fill(0, min($slots, 24), [
                'status' => 'nodata',
                'color' => '#6b7280',
                'title' => 'Aucune donnée',
            ]);
        }

        $duration = max(60, $from->diffInSeconds($to));
        $bucketSeconds = (int) max(60, ceil($duration / $slots));
        $buckets = [];

        foreach ($checks as $check) {
            if ($check->checked_at === null) {
                continue;
            }

            $key = (int) floor(($check->checked_at->timestamp - $from->timestamp) / $bucketSeconds);
            $buckets[$key] ??= ['up' => 0, 'down' => 0];
            if ($check->status === 'up') {
                $buckets[$key]['up']++;
            } else {
                $buckets[$key]['down']++;
            }
        }

        $nowKey = (int) floor((now()->timestamp - $from->timestamp) / $bucketSeconds);
        $maxKey = min((int) ceil($duration / $bucketSeconds), $nowKey, $slots - 1);
        $segments = [];
        $lastKnown = 'nodata';

        for ($i = 0; $i <= $maxKey; $i++) {
            $bucket = $buckets[$i] ?? null;

            if ($bucket === null) {
                if ($i === $maxKey && $lastKnown !== 'nodata') {
                    $segments[] = ['status' => $lastKnown, 'color' => $lastKnown === 'up' ? '#22c55e' : '#ef4444', 'title' => $lastKnown === 'up' ? 'Up' : 'Down'];
                } else {
                    $segments[] = ['status' => 'nodata', 'color' => '#6b7280', 'title' => 'Pas de données'];
                }
            } elseif ($bucket['down'] > 0) {
                $lastKnown = 'down';
                $segments[] = ['status' => 'down', 'color' => '#ef4444', 'title' => 'Down'];
            } else {
                $lastKnown = 'up';
                $segments[] = ['status' => 'up', 'color' => '#22c55e', 'title' => 'Up'];
            }
        }

        return $segments;
    }

    /**
     * @return array{uptime_percent: float, avg_ms: ?int, min_ms: ?int, max_ms: ?int, checks_total: int, avg_dns_ms: ?int, avg_tcp_ms: ?int, avg_ttfb_ms: ?int}
     */
    public function statsForMonitor(Monitor $monitor, int $days = 30): array
    {
        return $this->statsForPeriod($monitor, now()->subDays($days), now());
    }

    /**
     * @return array{uptime_percent: float, avg_ms: ?int, min_ms: ?int, max_ms: ?int, checks_total: int, avg_dns_ms: ?int, avg_tcp_ms: ?int, avg_ttfb_ms: ?int}
     */
    public function statsForPeriod(Monitor $monitor, Carbon $from, Carbon $to): array
    {
        $checks = MonitorCheck::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $from)
            ->where('checked_at', '<=', $to)
            ->get();

        $total = $checks->count();

        if ($total === 0) {
            return [
                'uptime_percent' => 0.0,
                'avg_ms' => null,
                'min_ms' => null,
                'max_ms' => null,
                'checks_total' => 0,
                'avg_dns_ms' => null,
                'avg_tcp_ms' => null,
                'avg_ttfb_ms' => null,
            ];
        }

        $up = $checks->where('status', 'up')->count();
        $latencies = $checks->pluck('response_ms')->filter(fn ($ms) => $ms !== null);
        $dnsValues = $checks->map(fn ($c) => $c->metrics['dns_ms'] ?? null)->filter(fn ($v) => is_numeric($v));
        $tcpValues = $checks->map(fn ($c) => $c->metrics['tcp_connect_ms'] ?? null)->filter(fn ($v) => is_numeric($v));
        $ttfbValues = $checks->map(fn ($c) => $c->metrics['ttfb_ms'] ?? null)->filter(fn ($v) => is_numeric($v));

        return [
            'uptime_percent' => round(($up / $total) * 100, 2),
            'avg_ms' => $latencies->isEmpty() ? null : (int) round($latencies->avg()),
            'min_ms' => $latencies->isEmpty() ? null : (int) $latencies->min(),
            'max_ms' => $latencies->isEmpty() ? null : (int) $latencies->max(),
            'checks_total' => $total,
            'avg_dns_ms' => $dnsValues->isEmpty() ? null : (int) round($dnsValues->avg()),
            'avg_tcp_ms' => $tcpValues->isEmpty() ? null : (int) round($tcpValues->avg()),
            'avg_ttfb_ms' => $ttfbValues->isEmpty() ? null : (int) round($ttfbValues->avg()),
        ];
    }
}

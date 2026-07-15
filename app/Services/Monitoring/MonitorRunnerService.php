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
            ->limit(2000)
            ->get();

        $checks = $this->downsampleChecks($checks, 400);

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
        $presetKey = strtolower($preset ?? '24h');
        $slots = $this->periods->timelineSlots($presetKey);

        $checks = MonitorCheck::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $from)
            ->where('checked_at', '<=', $to)
            ->orderBy('checked_at')
            ->get();

        if ($checks->isEmpty()) {
            return array_fill(0, $slots, $this->timelineSegment('nodata', $from, $from));
        }

        $fromTs = $from->timestamp;
        $toTs = $to->timestamp;
        $duration = max(60, $toTs - $fromTs);
        $bucketSeconds = $duration / $slots;

        /** @var array<int, array{up: int, down: int}>|array<int, null> $buckets */
        $buckets = array_fill(0, $slots, null);

        foreach ($checks as $check) {
            if ($check->checked_at === null) {
                continue;
            }

            $offset = max(0, $check->checked_at->timestamp - $fromTs);
            $index = (int) min($slots - 1, floor($offset / $bucketSeconds));

            $buckets[$index] ??= ['up' => 0, 'down' => 0];
            if ($check->status === 'up') {
                $buckets[$index]['up']++;
            } else {
                $buckets[$index]['down']++;
            }
        }

        $lastKnown = 'nodata';
        $segments = [];
        $lastDataIndex = null;

        for ($i = 0; $i < $slots; $i++) {
            $bucket = $buckets[$i];
            $segmentStart = $from->copy()->addSeconds((int) round($i * $bucketSeconds));
            $segmentEnd = $from->copy()->addSeconds((int) round(($i + 1) * $bucketSeconds));

            if ($bucket === null) {
                $segments[] = $this->timelineSegment('nodata', $segmentStart, $segmentEnd);
            } elseif ($bucket['down'] > 0) {
                $lastKnown = 'down';
                $lastDataIndex = $i;
                $segments[] = $this->timelineSegment('down', $segmentStart, $segmentEnd);
            } else {
                $lastKnown = 'up';
                $lastDataIndex = $i;
                $segments[] = $this->timelineSegment('up', $segmentStart, $segmentEnd);
            }
        }

        if ($lastDataIndex !== null && $lastKnown !== 'nodata') {
            for ($i = $lastDataIndex + 1; $i < $slots; $i++) {
                if (($buckets[$i] ?? null) === null) {
                    $segmentStart = $from->copy()->addSeconds((int) round($i * $bucketSeconds));
                    $segmentEnd = $from->copy()->addSeconds((int) round(($i + 1) * $bucketSeconds));
                    $segments[$i] = $this->timelineSegment($lastKnown, $segmentStart, $segmentEnd);
                }
            }
        }

        return $segments;
    }

    /**
     * @return list<array{label: string, percent: float}>
     */
    public function statusTimelineAxis(Carbon $from, Carbon $to, string $preset): array
    {
        return $this->periods->timelineAxisLabels($from, $to, $preset);
    }

    /**
     * @return array{status: string, color: string, title: string}
     */
    private function timelineSegment(string $status, Carbon $start, Carbon $end): array
    {
        $range = UserTimezone::format($start, 'H:i').' – '.UserTimezone::format($end, 'H:i');

        return match ($status) {
            'up' => ['status' => 'up', 'color' => '#22c55e', 'title' => "Up · {$range}"],
            'down' => ['status' => 'down', 'color' => '#ef4444', 'title' => "Down · {$range}"],
            default => ['status' => 'nodata', 'color' => '#6b7280', 'title' => "Pas de données · {$range}"],
        };
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

    /**
     * @param  \Illuminate\Support\Collection<int, MonitorCheck>  $checks
     * @return \Illuminate\Support\Collection<int, MonitorCheck>
     */
    private function downsampleChecks(\Illuminate\Support\Collection $checks, int $maxPoints): \Illuminate\Support\Collection
    {
        if ($checks->count() <= $maxPoints) {
            return $checks;
        }

        $step = (int) ceil($checks->count() / $maxPoints);
        $sampled = $checks->values()->filter(
            fn (MonitorCheck $check, int $index) => $index % $step === 0 || $index === $checks->count() - 1,
        );

        return $sampled->values();
    }
}

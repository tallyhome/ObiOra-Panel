<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\Server;
use App\Models\ServerMetricSample;
use App\Support\UserTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ServerMetricsService
{
    /**
     * @return array{from: Carbon, to: Carbon, resolution: string, label: string}
     */
    public function resolvePreset(string $preset): array
    {
        $to = UserTimezone::now();

        return match ($preset) {
            '1h' => ['from' => $to->copy()->subHour(), 'to' => $to, 'resolution' => '1m', 'label' => '1 heure'],
            '6h' => ['from' => $to->copy()->subHours(6), 'to' => $to, 'resolution' => '1m', 'label' => '6 heures'],
            '24h' => ['from' => $to->copy()->subDay(), 'to' => $to, 'resolution' => '1m', 'label' => '24 heures'],
            '3d' => ['from' => $to->copy()->subDays(3), 'to' => $to, 'resolution' => '5m', 'label' => '3 jours'],
            '7d' => ['from' => $to->copy()->subDays(7), 'to' => $to, 'resolution' => '5m', 'label' => '7 jours'],
            '30d', '1M' => ['from' => $to->copy()->subDays(30), 'to' => $to, 'resolution' => '1h', 'label' => '30 jours'],
            '3M' => ['from' => $to->copy()->subMonths(3), 'to' => $to, 'resolution' => '1h', 'label' => '3 mois'],
            '6M' => ['from' => $to->copy()->subMonths(6), 'to' => $to, 'resolution' => '1h', 'label' => '6 mois'],
            '1Y' => ['from' => $to->copy()->subYear(), 'to' => $to, 'resolution' => '1h', 'label' => '1 an'],
            default => ['from' => $to->copy()->subDay(), 'to' => $to, 'resolution' => '1m', 'label' => '24 heures'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Server $server, Carbon $from, Carbon $to, ?string $resolution = null): array
    {
        $resolution = $resolution ?? $this->autoResolution($from, $to);
        $samples = $this->loadSamples($server, $from, $to);
        $series = $this->buildSeries($samples, $resolution);

        $latest = $samples->last();

        return [
            'server' => $this->serializeServerHeader($server, $latest),
            'range' => [
                'from' => UserTimezone::format($from, 'd/m/Y H:i'),
                'to' => UserTimezone::format($to, 'd/m/Y H:i'),
                'resolution' => $resolution,
            ],
            'series' => $series,
            'stats' => $this->computeStats($samples),
            'partitions' => $this->extractPartitions($latest),
            'processes' => $this->extractProcesses($latest),
            'network' => $this->extractNetwork($latest),
        ];
    }

    private function autoResolution(Carbon $from, Carbon $to): string
    {
        $hours = $from->diffInHours($to);

        return match (true) {
            $hours <= 24 => '1m',
            $hours <= 168 => '5m',
            default => '1h',
        };
    }

    /**
     * @return Collection<int, ServerMetricSample>
     */
    private function loadSamples(Server $server, Carbon $from, Carbon $to): Collection
    {
        return ServerMetricSample::query()
            ->where('server_id', $server->id)
            ->whereBetween('sampled_at', [$from, $to])
            ->orderBy('sampled_at')
            ->limit(5000)
            ->get();
    }

    /**
     * @param  Collection<int, ServerMetricSample>  $samples
     * @return array<string, array{categories: list<string>, values: list<float|null>, avg: ?float, min: ?float, max: ?float}>
     */
    private function buildSeries(Collection $samples, string $resolution): array
    {
        $buckets = $this->bucketSamples($samples, $resolution);

        $categories = [];
        $cpu = [];
        $steal = [];
        $memory = [];
        $swap = [];
        $disk = [];
        $load1 = [];
        $load5 = [];
        $load15 = [];

        foreach ($buckets as $bucket) {
            $categories[] = UserTimezone::format($bucket['at'], 'd/m H:i');
            $cpu[] = $bucket['cpu_percent'];
            $steal[] = $bucket['cpu_steal_percent'];
            $memory[] = $bucket['memory_percent'];
            $swap[] = $bucket['swap_percent'];
            $disk[] = $bucket['disk_percent'];
            $load1[] = $bucket['load_1'];
            $load5[] = $bucket['load_5'];
            $load15[] = $bucket['load_15'];
        }

        return [
            'cpu' => $this->wrapSeries($categories, $cpu),
            'cpu_steal' => $this->wrapSeries($categories, $steal),
            'memory' => $this->wrapSeries($categories, $memory),
            'swap' => $this->wrapSeries($categories, $swap),
            'disk' => $this->wrapSeries($categories, $disk),
            'load' => [
                'categories' => $categories,
                'series' => [
                    ['name' => 'Load 1m', 'data' => $load1],
                    ['name' => 'Load 5m', 'data' => $load5],
                    ['name' => 'Load 15m', 'data' => $load15],
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, ServerMetricSample>  $samples
     * @return list<array<string, mixed>>
     */
    private function bucketSamples(Collection $samples, string $resolution): array
    {
        if ($samples->isEmpty()) {
            return [];
        }

        $seconds = match ($resolution) {
            '5m' => 300,
            '1h' => 3600,
            default => 60,
        };

        $buckets = [];

        foreach ($samples as $sample) {
            $ts = $sample->sampled_at?->timestamp ?? 0;
            $key = (int) floor($ts / $seconds);

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'at' => $sample->sampled_at,
                    'cpu_percent' => $sample->cpu_percent,
                    'cpu_steal_percent' => $sample->cpu_steal_percent,
                    'memory_percent' => $sample->memory_percent,
                    'swap_percent' => $sample->swap_percent,
                    'disk_percent' => $sample->disk_percent,
                    'load_1' => $sample->load_1,
                    'load_5' => $sample->load_5,
                    'load_15' => $sample->load_15,
                ];
            }
        }

        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * @param  list<string>  $categories
     * @param  list<float|null>  $values
     * @return array{categories: list<string>, values: list<float|null>, avg: ?float, min: ?float, max: ?float}
     */
    private function wrapSeries(array $categories, array $values): array
    {
        $numeric = array_values(array_filter($values, fn ($v) => $v !== null));

        return [
            'categories' => $categories,
            'values' => $values,
            'avg' => $numeric === [] ? null : round(array_sum($numeric) / count($numeric), 2),
            'min' => $numeric === [] ? null : round(min($numeric), 2),
            'max' => $numeric === [] ? null : round(max($numeric), 2),
        ];
    }

    /**
     * @param  Collection<int, ServerMetricSample>  $samples
     * @return array<string, float|null>
     */
    private function computeStats(Collection $samples): array
    {
        if ($samples->isEmpty()) {
            return ['uptime_seconds' => null];
        }

        $last = $samples->last();

        return [
            'uptime_seconds' => $last?->uptime_seconds !== null ? (float) $last->uptime_seconds : null,
            'samples' => $samples->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractPartitions(?ServerMetricSample $latest): array
    {
        if ($latest === null) {
            return [];
        }

        $partitions = ($latest->payload ?? [])['partitions'] ?? [];

        if (! is_array($partitions)) {
            return [];
        }

        $rows = [];

        foreach ($partitions as $mount => $data) {
            if (! is_array($data)) {
                continue;
            }

            $rows[] = [
                'mount' => (string) ($data['mount'] ?? $mount),
                'used_percent' => (float) ($data['used_percent'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractProcesses(?ServerMetricSample $latest): array
    {
        if ($latest === null) {
            return [];
        }

        $processes = ($latest->payload ?? [])['processes'] ?? [];

        return is_array($processes) ? array_slice($processes, 0, 20) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractNetwork(?ServerMetricSample $latest): array
    {
        if ($latest === null) {
            return [];
        }

        $network = ($latest->payload ?? [])['network'] ?? [];

        return is_array($network) ? $network : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeServerHeader(Server $server, ?ServerMetricSample $latest): array
    {
        $monitorMeta = ($server->metadata ?? [])['monitor_metrics'] ?? [];
        $agentMeta = ($server->metadata ?? [])['monitor_agent'] ?? [];

        return [
            'id' => $server->id,
            'name' => $server->name,
            'status' => $server->status->value,
            'last_seen' => UserTimezone::format($server->last_seen_at, 'd/m/Y H:i:s'),
            'os_label' => trim(($server->os_name ?? '').' '.($server->os_version ?? '')),
            'kernel' => ($server->metadata ?? [])['kernel'] ?? null,
            'agent_version' => $agentMeta['version'] ?? ($monitorMeta['agent_version'] ?? '—'),
            'ip_address' => $server->ip_address,
            'latest_cpu' => $latest?->cpu_percent,
            'latest_memory' => $latest?->memory_percent,
            'latest_disk' => $latest?->disk_percent,
        ];
    }
}

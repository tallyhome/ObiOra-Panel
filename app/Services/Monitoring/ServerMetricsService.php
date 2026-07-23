<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\Server;
use App\Models\ServerMetricSample;
use App\Support\UserTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ServerMetricsService
{
    /** Plafond de points graphiques (réactivité Livewire / ApexCharts). */
    private const MAX_CHART_POINTS = 360;

    public function __construct(
        private readonly MonitoringPeriodResolver $periods,
    ) {}

    /**
     * @return array{from: Carbon, to: Carbon, resolution: string, label: string}
     */
    public function resolvePreset(string $preset): array
    {
        $range = $this->periods->resolve($preset);

        return [
            'from' => $range['from'],
            'to' => $range['to'],
            'resolution' => $range['resolution'],
            'label' => $range['label'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Server $server, Carbon $from, Carbon $to, ?string $resolution = null): array
    {
        $resolution = $resolution ?? $this->autoResolution($from, $to);
        $bucketSeconds = $this->bucketSeconds($resolution);

        // Séries sans payload JSON (cause #1 de lenteur sur 3d/7d).
        $seriesRows = $this->loadAggregatedSeries($server, $from, $to, $bucketSeconds);
        $series = $this->seriesFromAggregatedRows($seriesRows);

        $latest = $this->loadLatestSample($server);
        $networkSeries = $this->buildNetworkSeriesSparse($server, $from, $to, $bucketSeconds);

        return [
            'server' => $this->serializeServerHeader($server, $latest),
            'sample_count' => $seriesRows->count(),
            'has_samples' => $seriesRows->isNotEmpty() || $latest !== null,
            'range' => [
                'from' => UserTimezone::format($from, 'd/m/Y H:i'),
                'to' => UserTimezone::format($to, 'd/m/Y H:i'),
                'resolution' => $resolution,
            ],
            'series' => $series,
            'stats' => $this->computeStatsFromLatest($latest, $seriesRows->count()),
            'partitions' => $this->extractPartitions($latest),
            'processes' => $this->extractProcesses($latest),
            'network' => $this->extractNetwork($latest),
            'network_series' => $networkSeries,
            'ip_addresses' => $this->extractIpAddresses($latest),
        ];
    }

    private function autoResolution(Carbon $from, Carbon $to): string
    {
        $hours = $from->diffInHours($to);

        return match (true) {
            $hours <= 6 => '1m',
            $hours <= 24 => '5m',
            $hours <= 72 => '15m',
            $hours <= 168 => '30m',
            default => '1h',
        };
    }

    private function bucketSeconds(string $resolution): int
    {
        return match ($resolution) {
            '5m' => 300,
            '15m' => 900,
            '30m' => 1800,
            '1h' => 3600,
            '6h' => 21600,
            '1d' => 86400,
            default => 60,
        };
    }

    /**
     * Agrégation SQL : ~quelques centaines de lignes, colonnes numériques uniquement.
     *
     * @return Collection<int, object>
     */
    private function loadAggregatedSeries(Server $server, Carbon $from, Carbon $to, int $bucketSeconds): Collection
    {
        $bucketSeconds = max(60, $bucketSeconds);
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $rows = DB::table('server_metric_samples')
                ->selectRaw('FLOOR(UNIX_TIMESTAMP(sampled_at) / ?) * ? as bucket_ts', [$bucketSeconds, $bucketSeconds])
                ->selectRaw('AVG(cpu_percent) as cpu_percent')
                ->selectRaw('AVG(cpu_steal_percent) as cpu_steal_percent')
                ->selectRaw('AVG(memory_percent) as memory_percent')
                ->selectRaw('AVG(swap_percent) as swap_percent')
                ->selectRaw('AVG(disk_percent) as disk_percent')
                ->selectRaw('AVG(load_1) as load_1')
                ->selectRaw('AVG(load_5) as load_5')
                ->selectRaw('AVG(load_15) as load_15')
                ->where('server_id', $server->id)
                ->where('sampled_at', '>=', $from)
                ->where('sampled_at', '<=', $to)
                ->groupBy('bucket_ts')
                ->orderBy('bucket_ts')
                ->limit(self::MAX_CHART_POINTS)
                ->get();

            return $rows->map(function (object $row) {
                $row->bucket_at = Carbon::createFromTimestamp((int) $row->bucket_ts)->toDateTimeString();

                return $row;
            });
        }

        // SQLite / tests : fallback PHP sans payload.
        return $this->aggregateInPhp(
            ServerMetricSample::query()
                ->where('server_id', $server->id)
                ->where('sampled_at', '>=', $from)
                ->where('sampled_at', '<=', $to)
                ->orderBy('sampled_at')
                ->limit(5000)
                ->get([
                    'sampled_at',
                    'cpu_percent',
                    'cpu_steal_percent',
                    'memory_percent',
                    'swap_percent',
                    'disk_percent',
                    'load_1',
                    'load_5',
                    'load_15',
                ]),
            $bucketSeconds
        );
    }

    /**
     * @param  Collection<int, ServerMetricSample>  $samples
     * @return Collection<int, object>
     */
    private function aggregateInPhp(Collection $samples, int $bucketSeconds): Collection
    {
        $buckets = [];

        foreach ($samples as $sample) {
            $ts = $sample->sampled_at?->timestamp ?? 0;
            $key = (int) floor($ts / $bucketSeconds);

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'at' => $ts,
                    'n' => 0,
                    'cpu_percent' => 0.0,
                    'cpu_steal_percent' => 0.0,
                    'memory_percent' => 0.0,
                    'swap_percent' => 0.0,
                    'disk_percent' => 0.0,
                    'load_1' => 0.0,
                    'load_5' => 0.0,
                    'load_15' => 0.0,
                ];
            }

            $buckets[$key]['n']++;
            $buckets[$key]['cpu_percent'] += (float) ($sample->cpu_percent ?? 0);
            $buckets[$key]['cpu_steal_percent'] += (float) ($sample->cpu_steal_percent ?? 0);
            $buckets[$key]['memory_percent'] += (float) ($sample->memory_percent ?? 0);
            $buckets[$key]['swap_percent'] += (float) ($sample->swap_percent ?? 0);
            $buckets[$key]['disk_percent'] += (float) ($sample->disk_percent ?? 0);
            $buckets[$key]['load_1'] += (float) ($sample->load_1 ?? 0);
            $buckets[$key]['load_5'] += (float) ($sample->load_5 ?? 0);
            $buckets[$key]['load_15'] += (float) ($sample->load_15 ?? 0);
        }

        ksort($buckets);
        $buckets = array_slice($buckets, 0, self::MAX_CHART_POINTS, true);

        return collect($buckets)->map(function (array $b) {
            $n = max(1, $b['n']);

            return (object) [
                'bucket_at' => Carbon::createFromTimestamp($b['at'])->toDateTimeString(),
                'cpu_percent' => $b['cpu_percent'] / $n,
                'cpu_steal_percent' => $b['cpu_steal_percent'] / $n,
                'memory_percent' => $b['memory_percent'] / $n,
                'swap_percent' => $b['swap_percent'] / $n,
                'disk_percent' => $b['disk_percent'] / $n,
                'load_1' => $b['load_1'] / $n,
                'load_5' => $b['load_5'] / $n,
                'load_15' => $b['load_15'] / $n,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, mixed>
     */
    private function seriesFromAggregatedRows(Collection $rows): array
    {
        $categories = [];
        $cpu = [];
        $steal = [];
        $memory = [];
        $swap = [];
        $disk = [];
        $load1 = [];
        $load5 = [];
        $load15 = [];

        foreach ($rows as $row) {
            $at = Carbon::parse((string) $row->bucket_at);
            $categories[] = UserTimezone::format($at, 'd/m H:i');
            $cpu[] = $this->nullableRound($row->cpu_percent ?? null);
            $steal[] = $this->nullableRound($row->cpu_steal_percent ?? null);
            $memory[] = $this->nullableRound($row->memory_percent ?? null);
            $swap[] = $this->nullableRound($row->swap_percent ?? null);
            $disk[] = $this->nullableRound($row->disk_percent ?? null);
            $load1[] = $this->nullableRound($row->load_1 ?? null, 3);
            $load5[] = $this->nullableRound($row->load_5 ?? null, 3);
            $load15[] = $this->nullableRound($row->load_15 ?? null, 3);
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

    private function nullableRound(mixed $value, int $precision = 2): ?float
    {
        if ($value === null) {
            return null;
        }

        return round((float) $value, $precision);
    }

    private function loadLatestSample(Server $server): ?ServerMetricSample
    {
        return ServerMetricSample::query()
            ->where('server_id', $server->id)
            ->orderByDesc('sampled_at')
            ->limit(1)
            ->first();
    }

    /**
     * Réseau : sous-échantillon espacé (évite de parser 5000 payloads processus).
     *
     * @return array<string, mixed>
     */
    private function buildNetworkSeriesSparse(Server $server, Carbon $from, Carbon $to, int $bucketSeconds): array
    {
        $empty = [
            'categories' => [],
            'rx_kbps' => [],
            'tx_kbps' => [],
            'tcp_connections' => [],
            'avg_rx' => null,
            'avg_tx' => null,
        ];

        $bucketSeconds = max(60, $bucketSeconds);
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $ids = DB::table('server_metric_samples')
                ->selectRaw('MIN(id) as id')
                ->where('server_id', $server->id)
                ->where('sampled_at', '>=', $from)
                ->where('sampled_at', '<=', $to)
                ->groupByRaw('FLOOR(UNIX_TIMESTAMP(sampled_at) / ?)', [$bucketSeconds])
                ->orderBy('id')
                ->limit(self::MAX_CHART_POINTS)
                ->pluck('id');

            if ($ids->count() < 2) {
                return $empty;
            }

            $samples = ServerMetricSample::query()
                ->whereIn('id', $ids)
                ->orderBy('sampled_at')
                ->get(['sampled_at', 'payload']);
        } else {
            $samples = ServerMetricSample::query()
                ->where('server_id', $server->id)
                ->where('sampled_at', '>=', $from)
                ->where('sampled_at', '<=', $to)
                ->orderBy('sampled_at')
                ->limit(self::MAX_CHART_POINTS)
                ->get(['sampled_at', 'payload']);
        }

        if ($samples->count() < 2) {
            return $empty;
        }

        $categories = [];
        $rxRates = [];
        $txRates = [];
        $tcpSeries = [];
        $prev = null;

        foreach ($samples as $sample) {
            $sampledAt = $sample->sampled_at instanceof Carbon
                ? $sample->sampled_at
                : Carbon::parse((string) $sample->sampled_at);

            $payload = $sample->payload ?? null;
            if (is_string($payload)) {
                $payload = json_decode($payload, true) ?: [];
            }
            if (! is_array($payload)) {
                $payload = [];
            }

            $network = $payload['network'] ?? [];
            $rx = 0;
            $tx = 0;

            if (is_array($network)) {
                foreach ($network as $iface) {
                    if (! is_array($iface)) {
                        continue;
                    }
                    $rx += (int) ($iface['rx'] ?? 0);
                    $tx += (int) ($iface['tx'] ?? 0);
                }
            }

            $tcp = (int) ($payload['tcp_connections'] ?? 0);

            if ($prev !== null) {
                $elapsed = max(1, $sampledAt->diffInSeconds($prev['at']));
                if ($elapsed <= $bucketSeconds * 3) {
                    $categories[] = UserTimezone::format($sampledAt, 'd/m H:i');
                    $rxRates[] = round(max(0, ($rx - $prev['rx']) * 8 / $elapsed / 1000), 2);
                    $txRates[] = round(max(0, ($tx - $prev['tx']) * 8 / $elapsed / 1000), 2);
                    $tcpSeries[] = $tcp > 0 ? $tcp : null;
                }
            }

            $prev = ['at' => $sampledAt, 'rx' => $rx, 'tx' => $tx];
        }

        $numericRx = array_values(array_filter($rxRates, fn ($v) => $v !== null));
        $numericTx = array_values(array_filter($txRates, fn ($v) => $v !== null));

        return [
            'categories' => $categories,
            'rx_kbps' => $rxRates,
            'tx_kbps' => $txRates,
            'tcp_connections' => $tcpSeries,
            'avg_rx' => $numericRx === [] ? null : round(array_sum($numericRx) / count($numericRx), 2),
            'avg_tx' => $numericTx === [] ? null : round(array_sum($numericTx) / count($numericTx), 2),
        ];
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
     * @return array<string, float|int|null>
     */
    private function computeStatsFromLatest(?ServerMetricSample $latest, int $sampleCount): array
    {
        if ($latest === null) {
            return ['uptime_seconds' => null, 'samples' => $sampleCount, 'tcp_connections' => 0];
        }

        return [
            'uptime_seconds' => $latest->uptime_seconds !== null ? (float) $latest->uptime_seconds : null,
            'samples' => $sampleCount,
            'tcp_connections' => (int) (($latest->payload ?? [])['tcp_connections'] ?? 0),
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
     * @return list<array{iface: string, address: string}>
     */
    private function extractIpAddresses(?ServerMetricSample $latest): array
    {
        if ($latest === null) {
            return [];
        }

        $ips = ($latest->payload ?? [])['ip_addresses'] ?? [];

        if (! is_array($ips)) {
            return [];
        }

        $rows = [];

        foreach ($ips as $data) {
            if (! is_array($data)) {
                continue;
            }

            $rows[] = [
                'iface' => (string) ($data['iface'] ?? '—'),
                'address' => (string) ($data['address'] ?? '—'),
            ];
        }

        return $rows;
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

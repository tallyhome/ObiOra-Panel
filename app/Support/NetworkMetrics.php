<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Server;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class NetworkMetrics
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?Server $server = null): array
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return $this->devSnapshot();
        }

        $serverId = $server?->id ?? 0;
        $interface = $this->primaryInterface();
        $counters = $this->readInterface($interface);

        if ($counters === null) {
            return $this->emptySnapshot();
        }

        $rates = $this->computeRates($serverId, $counters);
        $span = $this->accumulateSpan($serverId, $counters, $interface);

        return [
            'interface' => $interface,
            'rx_rate' => $rates['rx_rate'],
            'tx_rate' => $rates['tx_rate'],
            'rx_rate_human' => self::formatRate($rates['rx_rate']),
            'tx_rate_human' => self::formatRate($rates['tx_rate']),
            'span' => $span['rows'],
            'daily' => $span['daily'],
        ];
    }

    public static function formatRate(float $bytesPerSec): string
    {
        if ($bytesPerSec <= 0) {
            return '0 B/s';
        }

        if ($bytesPerSec >= 1_048_576) {
            return round($bytesPerSec / 1_048_576, 2).' MB/s';
        }

        if ($bytesPerSec >= 1024) {
            return round($bytesPerSec / 1024, 2).' KB/s';
        }

        return round($bytesPerSec, 0).' B/s';
    }

    public static function formatTotal(int|float $bytes): string
    {
        return DashboardHealth::formatBytes((int) $bytes);
    }

    /**
     * @return array<string, mixed>
     */
    public function emptySnapshot(): array
    {
        return [
            'interface' => '—',
            'rx_rate' => 0.0,
            'tx_rate' => 0.0,
            'rx_rate_human' => '0 B/s',
            'tx_rate_human' => '0 B/s',
            'span' => [],
            'daily' => [],
        ];
    }

    /**
     * @return array{rx: int, tx: int}|null
     */
    private function readInterface(string $interface): ?array
    {
        if (! is_readable('/proc/net/dev')) {
            return null;
        }

        $lines = file('/proc/net/dev', FILE_IGNORE_NEW_LINES) ?: [];

        foreach ($lines as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $stats] = array_map('trim', explode(':', $line, 2));
            if ($name !== $interface) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($stats)) ?: [];

            return [
                'rx' => (int) ($parts[0] ?? 0),
                'tx' => (int) ($parts[8] ?? 0),
            ];
        }

        return null;
    }

    private function primaryInterface(): string
    {
        $detected = trim((string) shell_exec(
            "ip -o route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if(\$i==\"dev\") print \$(i+1)}'"
        ));

        if ($detected !== '' && $detected !== 'lo') {
            return $detected;
        }

        if (! is_readable('/proc/net/dev')) {
            return 'eth0';
        }

        foreach (file('/proc/net/dev', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            $name = trim(explode(':', $line, 2)[0]);
            if ($name !== 'lo' && ! str_starts_with($name, 'docker') && ! str_starts_with($name, 'veth')) {
                return $name;
            }
        }

        return 'eth0';
    }

    /**
     * @param  array{rx: int, tx: int}  $counters
     * @return array{rx_rate: float, tx_rate: float}
     */
    private function computeRates(int $serverId, array $counters): array
    {
        $key = "obiora:net:last:{$serverId}";
        $now = microtime(true);
        $last = $this->cacheGet($key);

        $rxRate = 0.0;
        $txRate = 0.0;

        if (is_array($last)) {
            $elapsed = max(0.001, $now - (float) ($last['ts'] ?? $now));
            $rxRate = max(0, ($counters['rx'] - (int) ($last['rx'] ?? $counters['rx'])) / $elapsed);
            $txRate = max(0, ($counters['tx'] - (int) ($last['tx'] ?? $counters['tx'])) / $elapsed);
        }

        $this->cachePut($key, [
            'rx' => $counters['rx'],
            'tx' => $counters['tx'],
            'ts' => $now,
        ], 3600);

        return ['rx_rate' => $rxRate, 'tx_rate' => $txRate];
    }

    /**
     * @param  array{rx: int, tx: int}  $counters
     * @return array{rows: list<array{label: string, rx: int, tx: int}>, daily: list<array{date: string, rx: int, tx: int}>}
     */
    private function accumulateSpan(int $serverId, array $counters, string $interface): array
    {
        $key = "obiora:net:span:{$serverId}";
        $now = Carbon::now();

        /** @var array<string, mixed> $state */
        $state = $this->cacheGet($key, [
            'interface' => $interface,
            'last_rx' => $counters['rx'],
            'last_tx' => $counters['tx'],
            'hour' => ['start' => $now->copy()->startOfHour()->timestamp, 'rx' => 0, 'tx' => 0],
            'last_hour' => ['start' => $now->copy()->subHour()->startOfHour()->timestamp, 'rx' => 0, 'tx' => 0],
            'day' => ['start' => $now->copy()->startOfDay()->timestamp, 'rx' => 0, 'tx' => 0],
            'month' => ['start' => $now->copy()->startOfMonth()->timestamp, 'rx' => 0, 'tx' => 0],
            'all' => ['rx' => 0, 'tx' => 0],
            'daily' => [],
        ]);

        if (($state['interface'] ?? '') !== $interface) {
            $state['last_rx'] = $counters['rx'];
            $state['last_tx'] = $counters['tx'];
            $state['interface'] = $interface;
        }

        $deltaRx = max(0, $counters['rx'] - (int) ($state['last_rx'] ?? $counters['rx']));
        $deltaTx = max(0, $counters['tx'] - (int) ($state['last_tx'] ?? $counters['tx']));

        $hourStart = $now->copy()->startOfHour()->timestamp;
        if (($state['hour']['start'] ?? 0) !== $hourStart) {
            $state['last_hour'] = $state['hour'];
            $state['hour'] = ['start' => $hourStart, 'rx' => 0, 'tx' => 0];
        }

        $dayStart = $now->copy()->startOfDay()->timestamp;
        if (($state['day']['start'] ?? 0) !== $dayStart) {
            $yesterday = $now->copy()->subDay()->format('Y-m-d');
            /** @var array<string, array{rx: int, tx: int}> $daily */
            $daily = $state['daily'] ?? [];
            $daily[$yesterday] = [
                'rx' => (int) ($state['day']['rx'] ?? 0),
                'tx' => (int) ($state['day']['tx'] ?? 0),
            ];
            $state['daily'] = array_slice($daily, -14, 14, true);
            $state['day'] = ['start' => $dayStart, 'rx' => 0, 'tx' => 0];
        }

        $monthStart = $now->copy()->startOfMonth()->timestamp;
        if (($state['month']['start'] ?? 0) !== $monthStart) {
            $state['month'] = ['start' => $monthStart, 'rx' => 0, 'tx' => 0];
        }

        foreach (['hour', 'day', 'month'] as $period) {
            $state[$period]['rx'] = (int) ($state[$period]['rx'] ?? 0) + $deltaRx;
            $state[$period]['tx'] = (int) ($state[$period]['tx'] ?? 0) + $deltaTx;
        }

        $state['all']['rx'] = (int) ($state['all']['rx'] ?? 0) + $deltaRx;
        $state['all']['tx'] = (int) ($state['all']['tx'] ?? 0) + $deltaTx;
        $state['last_rx'] = $counters['rx'];
        $state['last_tx'] = $counters['tx'];

        $this->cacheForever($key, $state);

        $rows = [
            ['label' => 'Cette heure', 'rx' => (int) $state['hour']['rx'], 'tx' => (int) $state['hour']['tx']],
            ['label' => 'Heure précédente', 'rx' => (int) ($state['last_hour']['rx'] ?? 0), 'tx' => (int) ($state['last_hour']['tx'] ?? 0)],
            ['label' => 'Aujourd\'hui', 'rx' => (int) $state['day']['rx'], 'tx' => (int) $state['day']['tx']],
            ['label' => 'Ce mois', 'rx' => (int) $state['month']['rx'], 'tx' => (int) $state['month']['tx']],
            ['label' => 'Total', 'rx' => (int) $state['all']['rx'], 'tx' => (int) $state['all']['tx']],
        ];

        /** @var array<string, array{rx: int, tx: int}> $dailyMap */
        $dailyMap = $state['daily'] ?? [];
        $daily = [];
        krsort($dailyMap);
        foreach (array_slice($dailyMap, 0, 7, true) as $date => $totals) {
            $daily[] = ['date' => $date, 'rx' => (int) $totals['rx'], 'tx' => (int) $totals['tx']];
        }

        return ['rows' => $rows, 'daily' => $daily];
    }

    /**
     * @return array<string, mixed>
     */
    private function devSnapshot(): array
    {
        $rx = random_int(50_000, 2_000_000);
        $tx = random_int(30_000, 800_000);

        return [
            'interface' => 'eth0',
            'rx_rate' => (float) $rx,
            'tx_rate' => (float) $tx,
            'rx_rate_human' => self::formatRate((float) $rx),
            'tx_rate_human' => self::formatRate((float) $tx),
            'span' => [
                ['label' => 'Cette heure', 'rx' => 1_073_741_824, 'tx' => 536_870_912],
                ['label' => 'Heure précédente', 'rx' => 858_993_459, 'tx' => 402_653_184],
                ['label' => 'Aujourd\'hui', 'rx' => 5_368_709_120, 'tx' => 2_147_483_648],
                ['label' => 'Ce mois', 'rx' => 42_949_672_960, 'tx' => 17_179_869_184],
                ['label' => 'Total', 'rx' => 128_849_018_880, 'tx' => 51_539_607_552],
            ],
            'daily' => [
                ['date' => now()->subDay()->format('Y-m-d'), 'rx' => 4_294_967_296, 'tx' => 1_610_612_736],
                ['date' => now()->subDays(2)->format('Y-m-d'), 'rx' => 3_221_225_472, 'tx' => 1_288_490_188],
            ],
        ];
    }

    private function cacheGet(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }

    private function cachePut(string $key, mixed $value, int $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (Throwable) {
            // Redis indisponible — dashboard dégradé sans historique réseau
        }
    }

    private function cacheForever(string $key, mixed $value): void
    {
        try {
            Cache::forever($key, $value);
        } catch (Throwable) {
            // Redis indisponible — dashboard dégradé sans historique réseau
        }
    }
}

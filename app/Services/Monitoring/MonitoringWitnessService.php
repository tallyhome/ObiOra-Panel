<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\CrashHunterWitness;
use App\Models\Server;
use App\Support\ServerAgentStatus;
use App\Support\UserTimezone;

final class MonitoringWitnessService
{
    public function __construct(
        private readonly ServerAgentStatus $agents,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function fleetSummary(): array
    {
        $timeout = (int) config('crash_hunter.witness_stale_seconds', config('crash_hunter.witness_death_seconds', 90));

        return Server::query()
            ->orderByDesc('is_master')
            ->orderBy('name')
            ->get()
            ->map(function (Server $server) use ($timeout) {
                $flags = $this->agents->flags($server);

                if (! $flags['crash_hunter']) {
                    return [
                        'server_id' => $server->id,
                        'server_name' => $server->name,
                        'is_master' => $server->is_master,
                        'ping_ok' => in_array($server->status->value, ['online', 'degraded'], true),
                        'witness_status' => 'not_installed',
                        'witness_last_at' => '—',
                        'witness_gap_seconds' => null,
                        'stale' => false,
                        'anomaly' => false,
                        'remediation' => $server->is_master ? [] : [
                            'CrashHunter n\'est pas installé sur ce serveur.',
                            'Doctor & Suite → Déployer sur ce serveur (cocher CrashHunter).',
                        ],
                    ];
                }

                $witness = CrashHunterWitness::query()
                    ->where('server_id', $server->id)
                    ->orderByDesc('received_at')
                    ->first();

                $gap = $witness?->payload['gap_seconds'] ?? null;
                $status = $witness?->status ?? 'unknown';
                $stale = $witness === null
                    || ($witness->received_at && $witness->received_at->diffInSeconds(now()) > $timeout);

                $pingOk = in_array($server->status->value, ['online', 'degraded'], true);
                $anomaly = $pingOk && $stale && ! $server->is_master;

                return [
                    'server_id' => $server->id,
                    'server_name' => $server->name,
                    'is_master' => $server->is_master,
                    'ping_ok' => $pingOk,
                    'witness_status' => $stale && $witness !== null ? ($status === 'alive' ? 'stale' : $status) : $status,
                    'witness_last_at' => UserTimezone::format($witness?->received_at, 'd/m/Y H:i:s'),
                    'witness_gap_seconds' => $gap,
                    'stale' => $stale,
                    'anomaly' => $anomaly,
                    'remediation' => $anomaly ? [
                        'Vérifier que l\'agent CrashHunter tourne sur le serveur (systemctl status crashhunter).',
                        'Redémarrer : sudo systemctl restart crashhunter',
                        'Si absent : réinstaller via Doctor & Suite → Déployer sur ce serveur.',
                        'Un ping OK avec witness mort = kernel/agent gelé ou service crashé alors que le réseau répond encore.',
                    ] : [],
                ];
            })
            ->all();
    }

    public function anomalyCount(): int
    {
        return count(array_filter($this->fleetSummary(), fn (array $row) => $row['anomaly']));
    }
}

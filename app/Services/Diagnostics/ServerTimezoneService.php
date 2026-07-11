<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\DTOs\SshConnection;
use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\System\PrivilegedScriptRunner;
use App\Support\PanelLocalTarget;
use App\Support\TimezoneCatalog;
use InvalidArgumentException;

/**
 * Lit et applique le fuseau horaire système (panel local, SSH Doctor ou agent slave).
 */
final class ServerTimezoneService
{
    public function __construct(
        private readonly ServerManager $serverManager,
        private readonly SshRemoteExecutor $ssh,
        private readonly ServerSshKeyService $sshKeys,
    ) {}

    /**
     * @return array{timezone: ?string, datetime: ?string, ntp: ?string, label: ?string, error: ?string}
     */
    public function status(Server $server, ?SshConnection $connection = null, ?string $sshHost = null): array
    {
        if (PHP_OS_FAMILY !== 'Linux' && PanelLocalTarget::isPanelServer($server, $this->resolveHost($server, $sshHost))) {
            return [
                'timezone' => config('app.timezone', 'UTC'),
                'datetime' => now()->toIso8601String(),
                'ntp' => null,
                'label' => TimezoneCatalog::label((string) config('app.timezone', 'UTC')),
                'error' => null,
            ];
        }

        $result = $this->runScript($server, 'status', null, $connection, $sshHost);

        return array_merge($this->parseStatus($result['output']), [
            'error' => $result['success'] ? null : ($result['output'] ?: 'Impossible de lire le fuseau horaire du serveur.'),
        ]);
    }

    /**
     * @return array{success: bool, message: string, output: string, status: array{timezone: ?string, datetime: ?string, ntp: ?string, label: ?string, error: ?string}}
     */
    public function apply(Server $server, string $timezone, ?SshConnection $connection = null, ?string $sshHost = null): array
    {
        if (! TimezoneCatalog::isValid($timezone)) {
            throw new InvalidArgumentException('Fuseau horaire non autorisé.');
        }

        if (PHP_OS_FAMILY !== 'Linux' && PanelLocalTarget::isPanelServer($server, $this->resolveHost($server, $sshHost))) {
            return [
                'success' => false,
                'message' => 'Modification du fuseau indisponible sur cette plateforme.',
                'output' => '',
                'status' => $this->status($server, $connection, $sshHost),
            ];
        }

        $result = $this->runScript($server, 'set', $timezone, $connection, $sshHost);
        $status = $this->status($server, $connection, $sshHost);
        $success = $result['success'] && str_contains($result['output'], 'OBIORA_TZ_APPLIED:');

        return [
            'success' => $success,
            'message' => $success
                ? 'Fuseau horaire mis à jour : '.TimezoneCatalog::label($timezone).'.'
                : ($status['error'] ?? 'Échec de la mise à jour du fuseau horaire.'),
            'output' => $result['output'],
            'status' => $status,
        ];
    }

    /**
     * @return array{timezone: ?string, datetime: ?string, ntp: ?string, label: ?string}
     */
    private function parseStatus(string $output): array
    {
        $timezone = null;
        $datetime = null;
        $ntp = null;

        foreach (explode("\n", $output) as $line) {
            if (! str_starts_with($line, 'OBIORA_TZ_STATUS:')) {
                continue;
            }
            $pair = substr($line, strlen('OBIORA_TZ_STATUS:'));
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $value = trim($value);
            match ($key) {
                'timezone' => $timezone = $value !== '' ? $value : null,
                'datetime' => $datetime = $value !== '' ? $value : null,
                'ntp' => $ntp = $value !== '' ? $value : null,
                default => null,
            };
        }

        return [
            'timezone' => $timezone,
            'datetime' => $datetime,
            'ntp' => $ntp,
            'label' => $timezone !== null ? TimezoneCatalog::label($timezone) : null,
        ];
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function runScript(
        Server $server,
        string $action,
        ?string $timezone = null,
        ?SshConnection $connection = null,
        ?string $sshHost = null,
    ): array {
        $host = $this->resolveHost($server, $sshHost);

        if (PanelLocalTarget::isPanelServer($server, $host)) {
            $args = [$action];
            if ($timezone !== null) {
                $args[] = $timezone;
            }

            $runner = new PrivilegedScriptRunner($this->serverManager->executorFor($server));
            $result = $runner->run(base_path('agent/scripts/server-timezone.sh'), $args, 60);

            return [
                'success' => $result->successful,
                'output' => trim($result->output.$result->errorOutput),
            ];
        }

        $connection ??= $this->resolveSshConnection($server, $host);

        if ($connection !== null) {
            $command = $this->remoteScriptCommand($action, $timezone);
            $result = $this->ssh->run($connection, $command, 60);

            return [
                'success' => $result['success'],
                'output' => $result['output'],
            ];
        }

        $command = $this->remoteScriptCommand($action, $timezone);
        $result = $this->serverManager->executorFor($server)->run($command, ['timeout' => 60]);

        return [
            'success' => $result->successful,
            'output' => trim($result->output.$result->errorOutput),
        ];
    }

    private function remoteScriptCommand(string $action, ?string $timezone = null): string
    {
        $panelUrl = rtrim((string) config('app.url'), '/');
        $args = $action;

        if ($timezone !== null) {
            $args .= ' '.escapeshellarg($timezone);
        }

        return sprintf(
            'curl -fsSL %s/install/server-timezone.sh | sudo bash -s %s',
            escapeshellarg($panelUrl),
            $args,
        );
    }

    private function resolveHost(Server $server, ?string $sshHost): string
    {
        $sshHost = trim((string) ($sshHost ?? ''));
        if ($sshHost !== '') {
            return $sshHost;
        }

        $meta = $server->metadata ?? [];

        return trim((string) ($meta['doctor_deploy']['remote_host'] ?? $server->ip_address));
    }

    private function resolveSshConnection(Server $server, string $host): ?SshConnection
    {
        if (! $this->sshKeys->hasKey($server)) {
            return null;
        }

        if ($this->sshKeys->keyAppliesToHost($server, $host)) {
            return $this->sshKeys->connection($server, $host, 22, 'root');
        }

        return null;
    }
}

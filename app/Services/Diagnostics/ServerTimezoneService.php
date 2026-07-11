<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\System\PrivilegedScriptRunner;
use App\Support\TimezoneCatalog;
use InvalidArgumentException;

/**
 * Lit et applique le fuseau horaire système d'un serveur (local ou slave via agent).
 */
final class ServerTimezoneService
{
    public function __construct(
        private readonly ServerManager $serverManager,
    ) {}

    /**
     * @return array{timezone: ?string, datetime: ?string, ntp: ?string, label: ?string}
     */
    public function status(Server $server): array
    {
        if (PHP_OS_FAMILY !== 'Linux' && $this->isLocal($server)) {
            return [
                'timezone' => config('app.timezone', 'UTC'),
                'datetime' => now()->toIso8601String(),
                'ntp' => null,
                'label' => TimezoneCatalog::label((string) config('app.timezone', 'UTC')),
            ];
        }

        $result = $this->runScript($server, 'status');

        return $this->parseStatus($result['output']);
    }

    /**
     * @return array{success: bool, message: string, output: string, status: array{timezone: ?string, datetime: ?string, ntp: ?string, label: ?string}}
     */
    public function apply(Server $server, string $timezone): array
    {
        if (! TimezoneCatalog::isValid($timezone)) {
            throw new InvalidArgumentException('Fuseau horaire non autorisé.');
        }

        if (PHP_OS_FAMILY !== 'Linux' && $this->isLocal($server)) {
            return [
                'success' => false,
                'message' => 'Modification du fuseau indisponible sur cette plateforme.',
                'output' => '',
                'status' => $this->status($server),
            ];
        }

        $result = $this->runScript($server, 'set', $timezone);
        $status = $this->parseStatus($result['output']);
        $success = $result['success'] && str_contains($result['output'], 'OBIORA_TZ_APPLIED:');

        return [
            'success' => $success,
            'message' => $success
                ? 'Fuseau horaire mis à jour : '.TimezoneCatalog::label($timezone).'.'
                : 'Échec de la mise à jour du fuseau horaire.',
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
    private function runScript(Server $server, string $action, ?string $timezone = null): array
    {
        $args = [$action];
        if ($timezone !== null) {
            $args[] = $timezone;
        }

        $script = $this->scriptPath($server);
        $runner = new PrivilegedScriptRunner($this->serverManager->executorFor($server));
        $result = $runner->run($script, $args, 60);

        return [
            'success' => $result->successful,
            'output' => trim($result->output.$result->errorOutput),
        ];
    }

    private function scriptPath(Server $server): string
    {
        if ($this->isLocal($server)) {
            return base_path('agent/scripts/server-timezone.sh');
        }

        return '/opt/obiora-panel/agent/scripts/server-timezone.sh';
    }

    private function isLocal(Server $server): bool
    {
        return $server->is_master || $server->type->value === 'local';
    }
}

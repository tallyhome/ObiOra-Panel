<?php

declare(strict_types=1);

namespace App\Services\Database;

use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\System\PrivilegedScriptRunner;
use App\Support\ServerAccessHost;
use Illuminate\Support\Facades\Log;

final class PhpMyAdminService
{
    public function __construct(
        private readonly PrivilegedScriptRunner $scripts,
        private readonly ServerManager $serverManager,
        private readonly ServerAccessHost $accessHost,
    ) {}

    /**
     * @return array{status: string, port: int, url: ?string, message: string}
     */
    public function status(?Server $server = null): array
    {
        $port = (int) config('obiora.databases.phpmyadmin_port', 8099);
        $configured = trim((string) config('obiora.databases.phpmyadmin_url', ''));

        if ($configured !== '') {
            return [
                'status' => 'configured',
                'port' => $port,
                'url' => rtrim($configured, '/'),
                'message' => 'URL phpMyAdmin configurée.',
            ];
        }

        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null || ! $this->isLocal($server) || PHP_OS_FAMILY !== 'Linux') {
            return [
                'status' => 'unsupported',
                'port' => $port,
                'url' => null,
                'message' => 'phpMyAdmin auto-install disponible sur le serveur maître Linux.',
            ];
        }

        $script = base_path('agent/scripts/phpmyadmin-status.sh');
        if (! is_file($script)) {
            return [
                'status' => 'missing',
                'port' => $port,
                'url' => null,
                'message' => 'Script phpmyadmin-status.sh absent.',
            ];
        }

        $result = $this->scripts->run($script, [(string) $port]);
        $line = trim($result->output.$result->errorOutput);
        $host = $this->accessHost->resolve($server) ?: '127.0.0.1';

        if (str_starts_with($line, 'OK:running:')) {
            $port = (int) substr($line, strlen('OK:running:'));

            return [
                'status' => 'running',
                'port' => $port,
                'url' => $this->buildUrl($host, $port),
                'message' => 'phpMyAdmin actif.',
            ];
        }

        if (str_starts_with($line, 'OK:stopped:')) {
            $port = (int) substr($line, strlen('OK:stopped:'));

            return [
                'status' => 'stopped',
                'port' => $port,
                'url' => $this->buildUrl($host, $port),
                'message' => 'phpMyAdmin installé mais arrêté.',
            ];
        }

        return [
            'status' => 'absent',
            'port' => $port,
            'url' => $this->buildUrl($host, $port),
            'message' => 'phpMyAdmin non installé.',
        ];
    }

    /**
     * @return array{success: bool, url: ?string, message: string}
     */
    public function ensure(?Server $server = null): array
    {
        $server ??= $this->serverManager->getCurrentServer();
        $current = $this->status($server);

        if ($current['status'] === 'configured' || $current['status'] === 'running') {
            return [
                'success' => true,
                'url' => $current['url'],
                'message' => $current['message'],
            ];
        }

        if ($current['status'] === 'unsupported') {
            return [
                'success' => false,
                'url' => null,
                'message' => $current['message'],
            ];
        }

        $script = base_path('agent/scripts/phpmyadmin-ensure.sh');
        if (! is_file($script)) {
            return [
                'success' => false,
                'url' => null,
                'message' => 'Script phpmyadmin-ensure.sh introuvable.',
            ];
        }

        $port = (int) config('obiora.databases.phpmyadmin_port', 8099);
        $result = $this->scripts->run($script, [(string) $port], 300);
        $line = trim($result->output.$result->errorOutput);

        if (! $result->successful && ! str_starts_with($line, 'OK:')) {
            Log::warning('phpMyAdmin ensure failed', ['output' => $line]);

            return [
                'success' => false,
                'url' => null,
                'message' => 'Échec installation phpMyAdmin : '.$line,
            ];
        }

        $fresh = $this->status($server);

        return [
            'success' => $fresh['url'] !== null,
            'url' => $fresh['url'],
            'message' => $fresh['status'] === 'running'
                ? 'phpMyAdmin prêt (dernière version Docker).'
                : 'phpMyAdmin installé — '.$fresh['message'],
        ];
    }

    private function buildUrl(string $host, int $port): string
    {
        // Conteneur Docker expose HTTP sur le port hôte (pas le TLS du panel).
        return sprintf('http://%s:%d', $host, $port);
    }

    private function isLocal(Server $server): bool
    {
        return $server->is_master || $server->type->value === 'local';
    }
}

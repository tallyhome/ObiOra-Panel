<?php

declare(strict_types=1);

namespace App\Services\Web;

use App\Models\Server;
use App\Services\Core\ServerManager;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class WebsiteProvisioner
{
    public function __construct(
        private readonly ServerManager $serverManager,
    ) {}

    /**
     * @return array{success: bool, document_root: string, nginx_config_path: string, output: string}
     */
    public function create(Server $server, string $domain, string $phpVersion): array
    {
        $domain = $this->sanitizeDomain($domain);
        $phpVersion = $this->sanitizePhpVersion($phpVersion);
        $webRoot = (string) config('obiora.websites.web_root', '/var/www');

        if ($this->isLocal($server)) {
            $script = base_path('agent/scripts/website-create.sh');

            if (PHP_OS_FAMILY !== 'Linux') {
                return $this->devStubCreate($domain, $phpVersion, $webRoot);
            }

            $result = $this->serverManager->executorFor($server)->runScript(
                $script,
                [$domain, $phpVersion, $webRoot]
            );

            return $this->parseCreateOutput($result->successful, $result->output.$result->errorOutput);
        }

        return $this->remoteCreate($server, $domain, $phpVersion, $webRoot);
    }

    /**
     * @return array{success: bool, output: string}
     */
    public function delete(Server $server, string $domain): array
    {
        $domain = $this->sanitizeDomain($domain);
        $webRoot = (string) config('obiora.websites.web_root', '/var/www');

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return ['success' => true, 'output' => 'Suppression simulée (dev)'];
            }

            $script = base_path('agent/scripts/website-delete.sh');
            $result = $this->serverManager->executorFor($server)->runScript($script, [$domain, $webRoot]);

            return [
                'success' => $result->successful,
                'output' => $result->output.$result->errorOutput,
            ];
        }

        return $this->remoteDelete($server, $domain, $webRoot);
    }

    /**
     * @return array{success: bool, expires_at: ?string, output: string}
     */
    public function issueSsl(Server $server, string $domain, string $email): array
    {
        $domain = $this->sanitizeDomain($domain);
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ?: throw new InvalidArgumentException('Email SSL invalide.');
        $webRoot = (string) config('obiora.websites.web_root', '/var/www');

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return [
                    'success' => true,
                    'expires_at' => now()->addMonths(3)->toIso8601String(),
                    'output' => 'SSL simulé (dev)',
                ];
            }

            $script = base_path('agent/scripts/website-ssl.sh');
            $args = implode(' ', array_map('escapeshellarg', [$domain, $email, $webRoot]));
            $result = $this->serverManager->executorFor($server)->run(
                "bash ".escapeshellarg($script)." {$args}",
                ['timeout' => 300]
            );

            return $this->parseSslOutput($result->successful, $result->output.$result->errorOutput);
        }

        return $this->remoteSsl($server, $domain, $email, $webRoot);
    }

    private function isLocal(Server $server): bool
    {
        return $server->is_master || $server->type->value === 'local';
    }

    private function sanitizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if (! preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)+$/', $domain)) {
            throw new InvalidArgumentException('Nom de domaine invalide.');
        }

        return $domain;
    }

    private function sanitizePhpVersion(string $version): string
    {
        $allowed = config('obiora.websites.php_versions', ['8.3']);

        if (! in_array($version, $allowed, true)) {
            throw new InvalidArgumentException('Version PHP non supportée.');
        }

        return $version;
    }

    /**
     * @return array{success: bool, document_root: string, nginx_config_path: string, output: string}
     */
    private function parseCreateOutput(bool $success, string $output): array
    {
        if ($success && preg_match('/OK:(.+):(.+)/', $output, $m)) {
            return [
                'success' => true,
                'document_root' => trim($m[1]),
                'nginx_config_path' => trim($m[2]),
                'output' => $output,
            ];
        }

        return [
            'success' => false,
            'document_root' => '',
            'nginx_config_path' => '',
            'output' => $output,
        ];
    }

    /**
     * @return array{success: bool, expires_at: ?string, output: string}
     */
    private function parseSslOutput(bool $success, string $output): array
    {
        $expires = null;

        if (preg_match('/OK:(.+)/', $output, $m)) {
            $raw = trim($m[1]);
            if ($raw !== '') {
                try {
                    $expires = \Carbon\Carbon::parse($raw)->toIso8601String();
                } catch (\Throwable) {
                    $expires = $raw;
                }
            }
        }

        return [
            'success' => $success && str_contains($output, 'OK:'),
            'expires_at' => $expires,
            'output' => $output,
        ];
    }

    /**
     * @return array{success: bool, document_root: string, nginx_config_path: string, output: string}
     */
    private function devStubCreate(string $domain, string $phpVersion, string $webRoot): array
    {
        return [
            'success' => true,
            'document_root' => "{$webRoot}/{$domain}/public",
            'nginx_config_path' => "/etc/nginx/sites-available/obiora-".str_replace('.', '-', $domain),
            'output' => "OK:{$webRoot}/{$domain}/public:/etc/nginx/sites-available/obiora-{$domain} (dev stub PHP {$phpVersion})",
        ];
    }

    /**
     * @return array{success: bool, document_root: string, nginx_config_path: string, output: string}
     */
    private function remoteCreate(Server $server, string $domain, string $phpVersion, string $webRoot): array
    {
        try {
            $response = $this->agentRequest($server, 'POST', '/api/v1/websites', [
                'domain' => $domain,
                'php_version' => $phpVersion,
                'web_root' => $webRoot,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'document_root' => '', 'nginx_config_path' => '', 'output' => $e->getMessage()];
        }

        return [
            'success' => (bool) $response->json('success', false),
            'document_root' => (string) $response->json('document_root', ''),
            'nginx_config_path' => (string) $response->json('nginx_config_path', ''),
            'output' => (string) $response->json('output', $response->body()),
        ];
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function remoteDelete(Server $server, string $domain, string $webRoot): array
    {
        try {
            $response = $this->agentRequest($server, 'DELETE', '/api/v1/websites', [
                'domain' => $domain,
                'web_root' => $webRoot,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'output' => $e->getMessage()];
        }

        return [
            'success' => (bool) $response->json('success', false),
            'output' => (string) $response->json('output', $response->body()),
        ];
    }

    /**
     * @return array{success: bool, expires_at: ?string, output: string}
     */
    private function remoteSsl(Server $server, string $domain, string $email, string $webRoot): array
    {
        try {
            $response = $this->agentRequest($server, 'POST', '/api/v1/websites/ssl', [
                'domain' => $domain,
                'email' => $email,
                'web_root' => $webRoot,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'expires_at' => null, 'output' => $e->getMessage()];
        }

        return [
            'success' => (bool) $response->json('success', false),
            'expires_at' => $response->json('expires_at'),
            'output' => (string) $response->json('output', $response->body()),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function agentRequest(Server $server, string $method, string $uri, array $payload = []): \Illuminate\Http\Client\Response
    {
        $node = $server->primaryNode;

        if ($node === null) {
            throw new InvalidArgumentException('Nœud agent introuvable pour ce serveur.');
        }

        $host = $node->host ?? $server->ip_address;
        $port = $node->port ?? 9100;
        $url = "http://{$host}:{$port}{$uri}";

        $client = Http::timeout($method === 'POST' && str_contains($uri, 'ssl') ? 300 : 120)
            ->withToken($server->agent_token);

        return match ($method) {
            'POST' => $client->post($url, $payload),
            'DELETE' => $client->withBody(json_encode($payload), 'application/json')->delete($url),
            default => $client->get($url, $payload),
        };
    }
}

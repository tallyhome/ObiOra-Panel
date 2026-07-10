<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\DTOs\SshConnection;
use App\Models\Server;
use App\Services\System\PrivilegedScriptRunner;
use App\Support\PanelLocalTarget;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Génère et stocke une clé SSH dédiée par serveur (privée chiffrée).
 */
final class ServerSshKeyService
{
    public function __construct(
        private readonly PrivilegedScriptRunner $scripts,
    ) {}

    public function hasKey(Server $server): bool
    {
        return $this->privateKey($server) !== null;
    }

    public function isInstalledOnRemote(Server $server): bool
    {
        $meta = ($server->metadata ?? [])['ssh_deploy'] ?? [];

        return $this->hasKey($server) && ! empty($meta['installed_on_remote_at']);
    }

    public function publicKey(Server $server): ?string
    {
        $key = ($server->metadata ?? [])['ssh_deploy']['public_key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function fingerprint(Server $server): ?string
    {
        $fp = ($server->metadata ?? [])['ssh_deploy']['fingerprint'] ?? null;

        return is_string($fp) && $fp !== '' ? $fp : null;
    }

    public function privateKey(Server $server): ?string
    {
        $encrypted = ($server->metadata ?? [])['ssh_deploy']['private_key'] ?? null;

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            Log::warning('SSH private key decrypt failed', ['server_id' => $server->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Génère une paire ed25519 et la stocke sur le serveur panel.
     */
    public function generate(Server $server): string
    {
        $tmpDir = sys_get_temp_dir().'/obiora_ssh_'.$server->id.'_'.bin2hex(random_bytes(4));
        if (! mkdir($tmpDir, 0700, true) && ! is_dir($tmpDir)) {
            throw new \RuntimeException('Impossible de créer le répertoire temporaire SSH.');
        }

        $keyPath = $tmpDir.'/obiora_ed25519';

        try {
            $process = new Process([
                'ssh-keygen',
                '-t', 'ed25519',
                '-f', $keyPath,
                '-N', '',
                '-C', 'obiora-panel-server-'.$server->id,
                '-q',
            ]);
            $process->setTimeout(30);
            $process->mustRun();

            $private = (string) file_get_contents($keyPath);
            $public = trim((string) file_get_contents($keyPath.'.pub'));

            if ($private === '' || $public === '') {
                throw new \RuntimeException('Génération de clé SSH incomplète.');
            }

            $fingerprint = $this->fingerprintFromPublic($public);

            $server->forceFill([
                'metadata' => array_merge($server->metadata ?? [], [
                    'ssh_deploy' => [
                        'public_key' => $public,
                        'private_key' => Crypt::encryptString($private),
                        'fingerprint' => $fingerprint,
                        'generated_at' => now()->toIso8601String(),
                        'installed_on_remote_at' => null,
                    ],
                ]),
            ])->save();

            return $public;
        } finally {
            @unlink($keyPath);
            @unlink($keyPath.'.pub');
            @rmdir($tmpDir);
        }
    }

    public function markInstalledOnRemote(Server $server): void
    {
        $meta = ($server->metadata ?? [])['ssh_deploy'] ?? [];
        if ($meta === []) {
            return;
        }

        $meta['installed_on_remote_at'] = now()->toIso8601String();

        $server->forceFill([
            'metadata' => array_merge($server->metadata ?? [], ['ssh_deploy' => $meta]),
        ])->save();
    }

    public function connection(Server $server, string $host, int $port, string $username): ?SshConnection
    {
        $privateKey = $this->privateKey($server);

        if ($privateKey === null) {
            return null;
        }

        return new SshConnection(
            host: $host,
            port: $port,
            username: $username,
            privateKey: $privateKey,
        );
    }

    /**
     * Installe la clé publique sur le serveur distant (auth mot de passe ponctuelle).
     *
     * @return array{success: bool, output: string}
     */
    public function installPublicKeyOnRemote(
        Server $server,
        SshConnection $bootstrap,
        DoctorRemoteDeployService $deploy,
    ): array {
        $publicKey = $this->publicKey($server);

        if ($publicKey === null) {
            return ['success' => false, 'output' => 'Générez d\'abord une clé SSH dédiée.'];
        }

        $marker = 'obiora-panel-server-'.$server->id;
        $escapedKey = escapeshellarg($publicKey);
        $command = <<<SH
mkdir -p ~/.ssh && chmod 700 ~/.ssh && \
grep -qF {$escapedKey} ~/.ssh/authorized_keys 2>/dev/null || echo {$escapedKey} >> ~/.ssh/authorized_keys && \
chmod 600 ~/.ssh/authorized_keys && echo "OBIORA_KEY_INSTALLED:{$marker}"
SH;

        $result = $deploy->runRemote($bootstrap, $command);

        if ($result['success'] && str_contains($result['output'], 'OBIORA_KEY_INSTALLED')) {
            $this->markInstalledOnRemote($server);

            return ['success' => true, 'output' => 'Clé SSH installée sur le serveur distant.'];
        }

        return [
            'success' => false,
            'output' => $result['output'] ?: 'Échec installation clé publique.',
        ];
    }

    /**
     * Installe la clé publique sur le serveur local du panel (sans client SSH).
     *
     * @return array{success: bool, output: string}
     */
    public function installPublicKeyLocally(Server $server): array
    {
        $publicKey = $this->publicKey($server);

        if ($publicKey === null) {
            return ['success' => false, 'output' => 'Générez d\'abord une clé SSH dédiée.'];
        }

        $marker = 'obiora-panel-server-'.$server->id;
        $script = base_path('agent/scripts/ssh-authorize-panel-key.sh');

        if (! is_file($script)) {
            return ['success' => false, 'output' => 'Script ssh-authorize-panel-key.sh introuvable.'];
        }

        $result = $this->scripts->run($script, [$publicKey, $marker], 60);
        $output = trim($result->output.$result->errorOutput);

        if ($result->successful && str_contains($output, 'OBIORA_KEY_INSTALLED')) {
            $this->markInstalledOnRemote($server);

            return ['success' => true, 'output' => 'Clé SSH installée sur le serveur local.'];
        }

        return [
            'success' => false,
            'output' => $output !== '' ? $output : 'Échec installation clé publique locale.',
        ];
    }

    public function isLocalPanelTarget(Server $server, string $host): bool
    {
        return PanelLocalTarget::isPanelServer($server, $host);
    }

    private function fingerprintFromPublic(string $publicKey): string
    {
        $process = new Process(['ssh-keygen', '-lf', '-']);
        $process->setInput($publicKey);
        $process->run();

        if ($process->isSuccessful()) {
            $parts = preg_split('/\s+/', trim($process->getOutput()));

            return $parts[1] ?? 'unknown';
        }

        return substr(hash('sha256', $publicKey), 0, 16);
    }
}

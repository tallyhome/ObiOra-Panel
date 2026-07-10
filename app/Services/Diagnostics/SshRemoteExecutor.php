<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\DTOs\SshConnection;
use Illuminate\Support\Facades\Log;
use phpseclib3\Exception\UnableToConnectException;
use phpseclib3\Net\SSH2;
use Symfony\Component\Process\Process;

/**
 * Exécute des commandes SSH depuis le panel (clé native ou mot de passe via phpseclib).
 */
final class SshRemoteExecutor
{
    /**
     * PHP-FPM (apache) n'a souvent pas de $HOME writable — ssh échoue sans cela.
     *
     * @return array<string, string>
     */
    public static function runtimeEnv(): array
    {
        $dir = storage_path('app/ssh');

        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            $dir = sys_get_temp_dir().'/obiora_ssh';
            if (! is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
        }

        return [
            'HOME' => $dir,
            'USER' => 'obiora-panel',
            'SSH_AUTH_SOCK' => '',
        ];
    }

    public function run(SshConnection $ssh, string $remoteCommand, int $timeout = 300): array
    {
        if ($ssh->privateKey !== null && $ssh->privateKey !== '') {
            return $this->runViaNativeSsh($ssh, $remoteCommand, $timeout);
        }

        if ($ssh->password !== null && $ssh->password !== '') {
            return $this->runViaPhpseclib($ssh, $remoteCommand, $timeout);
        }

        return [
            'success' => false,
            'output' => 'Authentification SSH requise : saisissez un mot de passe ou installez une clé dédiée.',
            'exit_code' => 1,
        ];
    }

    /**
     * @return array{success: bool, output: string, exit_code: int}
     */
    private function runViaPhpseclib(SshConnection $ssh, string $remoteCommand, int $timeout): array
    {
        try {
            $connection = new SSH2($ssh->host, $ssh->port);
            $connection->setTimeout($timeout);

            if (! $connection->login($ssh->username, $ssh->password)) {
                return [
                    'success' => false,
                    'output' => 'Authentification SSH refusée (utilisateur ou mot de passe incorrect).',
                    'exit_code' => 1,
                ];
            }

            $output = $connection->exec($remoteCommand);
            $exitCode = $connection->getExitStatus();

            return [
                'success' => $exitCode === 0,
                'output' => trim((string) $output),
                'exit_code' => $exitCode ?? 1,
            ];
        } catch (UnableToConnectException $e) {
            return [
                'success' => false,
                'output' => 'Connexion SSH impossible : '.$e->getMessage(),
                'exit_code' => 1,
            ];
        } catch (\Throwable $e) {
            Log::warning('SSH phpseclib failed', ['host' => $ssh->host, 'error' => $e->getMessage()]);

            return [
                'success' => false,
                'output' => $e->getMessage(),
                'exit_code' => 1,
            ];
        }
    }

    /**
     * @return array{success: bool, output: string, exit_code: int}
     */
    private function runViaNativeSsh(SshConnection $ssh, string $remoteCommand, int $timeout): array
    {
        $keyFile = null;

        try {
            $keyFile = tempnam(sys_get_temp_dir(), 'obiora_ssh_');
            if ($keyFile === false) {
                return ['success' => false, 'output' => 'Impossible de créer un fichier clé temporaire.', 'exit_code' => 1];
            }

            file_put_contents($keyFile, $ssh->privateKey);
            chmod($keyFile, 0600);

            $sshDir = self::runtimeEnv()['HOME'];
            $knownHosts = $sshDir.'/known_hosts';

            $process = new Process([
                'ssh',
                '-p', (string) $ssh->port,
                '-i', $keyFile,
                '-o', 'StrictHostKeyChecking=accept-new',
                '-o', 'UserKnownHostsFile='.$knownHosts,
                '-o', 'ConnectTimeout=30',
                '-o', 'LogLevel=ERROR',
                '-o', 'BatchMode=yes',
                $ssh->username.'@'.$ssh->host,
                $remoteCommand,
            ], null, self::runtimeEnv());
            $process->setTimeout($timeout);
            $process->run();

            return [
                'success' => $process->isSuccessful(),
                'output' => trim($process->getOutput()."\n".$process->getErrorOutput()),
                'exit_code' => $process->getExitCode() ?? 1,
            ];
        } catch (\Throwable $e) {
            Log::warning('SSH native failed', ['host' => $ssh->host, 'error' => $e->getMessage()]);

            return ['success' => false, 'output' => $e->getMessage(), 'exit_code' => 1];
        } finally {
            if ($keyFile !== null && is_file($keyFile)) {
                @unlink($keyFile);
            }
        }
    }
}

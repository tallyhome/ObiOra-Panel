<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Enums\ServerType;
use App\Models\Server;
use App\Services\Core\ServerManager;
use Illuminate\Support\Str;

/**
 * Associe une cible SSH (IP) au bon enregistrement serveur panel pour Doctor & Suite.
 */
final class DoctorDeployTargetResolver
{
    public function __construct(
        private readonly ServerManager $serverManager,
    ) {}

    /**
     * Retourne le serveur panel dont l'IP correspond à la cible SSH,
     * ou crée un enregistrement dédié/VPS si absent.
     */
    public function resolve(string $sshHost, ?string $remoteHostname = null, ?int $preferredServerId = null): Server
    {
        $sshHost = trim($sshHost);

        $byIp = Server::query()->where('ip_address', $sshHost)->first();

        if ($byIp !== null) {
            return $byIp;
        }

        if ($preferredServerId !== null) {
            $preferred = Server::query()->find($preferredServerId);

            if ($preferred !== null && $preferred->ip_address === $sshHost) {
                return $preferred;
            }
        }

        $label = $this->humanLabel($remoteHostname, $sshHost);

        return $this->serverManager->createRemote([
            'name' => $label,
            'hostname' => $remoteHostname ?: $sshHost,
            'ip_address' => $sshHost,
            'type' => ServerType::Dedicated,
        ]);
    }

    public function findByIp(string $sshHost): ?Server
    {
        return Server::query()->where('ip_address', trim($sshHost))->first();
    }

    private function humanLabel(?string $hostname, string $ip): string
    {
        $hostname = trim((string) $hostname);

        if ($hostname !== '' && ! filter_var($hostname, FILTER_VALIDATE_IP)) {
            return Str::limit($hostname, 64, '');
        }

        return 'Serveur '.$ip;
    }
}

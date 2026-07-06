<?php

declare(strict_types=1);

namespace App\Services\Web;

use App\Enums\WebsiteStatus;
use App\Models\Server;
use App\Models\Website;
use App\Services\Core\ServerManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class WebsiteManager
{
    public function __construct(
        private readonly ServerManager $serverManager,
        private readonly WebsiteProvisioner $provisioner,
    ) {}

    /**
     * @return Collection<int, Website>
     */
    public function forServer(?Server $server = null): Collection
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return collect();
        }

        return Website::query()
            ->where('server_id', $server->id)
            ->orderBy('domain')
            ->get();
    }

    /**
     * @param  array{domain: string, php_version?: string, ssl_email?: string|null, enable_ssl?: bool}  $data
     */
    public function create(array $data, ?Server $server = null): Website
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            throw new InvalidArgumentException('Aucun serveur sélectionné.');
        }

        $domain = strtolower(trim($data['domain']));
        $phpVersion = $data['php_version'] ?? config('obiora.websites.default_php', '8.3');
        $enableSsl = (bool) ($data['enable_ssl'] ?? false);
        $sslEmail = $data['ssl_email'] ?? null;

        if ($enableSsl && empty($sslEmail)) {
            throw new InvalidArgumentException('Email requis pour activer SSL.');
        }

        if (Website::query()->where('server_id', $server->id)->where('domain', $domain)->exists()) {
            throw new InvalidArgumentException("Le domaine « {$domain} » existe déjà sur ce serveur.");
        }

        $website = Website::query()->create([
            'server_id' => $server->id,
            'domain' => $domain,
            'document_root' => '',
            'php_version' => $phpVersion,
            'ssl_enabled' => false,
            'ssl_email' => $sslEmail,
            'status' => WebsiteStatus::Pending,
        ]);

        $result = $this->provisioner->create($server, $domain, $phpVersion);

        if (! $result['success']) {
            $website->update([
                'status' => WebsiteStatus::Error,
                'metadata' => ['error' => $result['output']],
            ]);

            Log::channel('provisioning')->error('Website creation failed', [
                'domain' => $domain,
                'server_id' => $server->id,
                'output' => $result['output'],
            ]);

            throw new InvalidArgumentException('Échec du provisionnement : '.trim($result['output']));
        }

        $website->update([
            'document_root' => $result['document_root'],
            'nginx_config_path' => $result['nginx_config_path'],
            'status' => WebsiteStatus::Active,
        ]);

        if ($enableSsl && $sslEmail) {
            $this->enableSsl($website, $sslEmail);
            $website->refresh();
        }

        return $website;
    }

    public function enableSsl(Website $website, ?string $email = null): Website
    {
        $email ??= $website->ssl_email;

        if (empty($email)) {
            throw new InvalidArgumentException('Email SSL requis.');
        }

        if ($website->ssl_enabled) {
            return $website;
        }

        $server = $website->server;
        $result = $this->provisioner->issueSsl($server, $website->domain, $email);

        if (! $result['success']) {
            Log::channel('provisioning')->error('SSL issuance failed', [
                'domain' => $website->domain,
                'output' => $result['output'],
            ]);

            throw new InvalidArgumentException('Échec SSL : '.trim($result['output']));
        }

        $website->update([
            'ssl_enabled' => true,
            'ssl_email' => $email,
            'ssl_expires_at' => $result['expires_at'] ? \Carbon\Carbon::parse($result['expires_at']) : null,
        ]);

        return $website->fresh() ?? $website;
    }

    public function delete(Website $website): void
    {
        $server = $website->server;
        $result = $this->provisioner->delete($server, $website->domain);

        if (! $result['success']) {
            throw new InvalidArgumentException('Échec suppression : '.trim($result['output']));
        }

        $website->delete();
    }
}

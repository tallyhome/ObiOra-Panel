<?php

declare(strict_types=1);

namespace App\Services\Applications;

use App\DTOs\ApplicationPackage;
use App\Enums\ApplicationStatus;
use App\Jobs\ApplicationInstallJob;
use App\Models\InstalledApplication;
use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Support\ServerAccessHost;
use App\Services\System\PrivilegedScriptRunner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class ApplicationManager
{
    private const APP_ACTIONS = ['start', 'stop', 'restart'];

    public function __construct(
        private readonly ServerManager $serverManager,
        private readonly ApplicationCatalog $catalog,
        private readonly PrivilegedScriptRunner $scripts,
        private readonly ServerAccessHost $accessHost,
    ) {}

    /**
     * @return Collection<int, InstalledApplication>
     */
    public function installed(?Server $server = null): Collection
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return collect();
        }

        return InstalledApplication::query()
            ->where('server_id', $server->id)
            ->orderBy('name')
            ->get();
    }

    public function isInstalled(string $slug, ?Server $server = null): bool
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return false;
        }

        return InstalledApplication::query()
            ->where('server_id', $server->id)
            ->where('slug', $slug)
            ->where('status', ApplicationStatus::Installed)
            ->exists();
    }

    public function progressCacheKey(int $serverId, string $slug): string
    {
        return "obiora_progress:marketplace:{$serverId}:{$slug}";
    }

    public function installOptionsCacheKey(int $serverId, string $slug): string
    {
        return "obiora_marketplace_install_options:{$serverId}:{$slug}";
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    public function validateInstallOptions(ApplicationPackage $package, array $options): array
    {
        $validated = [];
        $errors = [];

        foreach ($package->installOptions() as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $value = trim((string) ($options[$name] ?? ''));
            $required = (bool) ($field['required'] ?? false);
            $label = (string) ($field['label'] ?? $name);

            if ($required && $value === '') {
                $errors[] = "« {$label} » est requis.";

                continue;
            }

            $min = isset($field['min']) ? (int) $field['min'] : null;
            if ($min !== null && $value !== '' && mb_strlen($value) < $min) {
                $errors[] = "« {$label} » doit contenir au moins {$min} caractères.";
            }

            if (($field['type'] ?? '') === 'password' && isset($field['confirm'])) {
                $confirmName = (string) $field['confirm'];
                if ($value !== trim((string) ($options[$confirmName] ?? ''))) {
                    $errors[] = 'Les mots de passe ne correspondent pas.';
                }
            }

            if (isset($field['matches'])) {
                $matchName = (string) $field['matches'];
                if ($value !== trim((string) ($options[$matchName] ?? ''))) {
                    $errors[] = 'Les mots de passe ne correspondent pas.';
                }
            }

            if (! isset($field['matches'])) {
                $validated[$name] = $value;
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return $validated;
    }

    /**
     * @param  array<string, string>  $options
     */
    public function queueInstall(string $slug, ?Server $server = null, array $options = []): InstalledApplication
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            throw new InvalidArgumentException('Aucun serveur sélectionné.');
        }

        $package = $this->catalog->find($slug);

        if ($package === null) {
            throw new InvalidArgumentException("Application « {$slug} » introuvable dans le catalogue.");
        }

        if ($this->isInstalled($slug, $server)) {
            throw new InvalidArgumentException("« {$package->name()} » est déjà installé sur ce serveur.");
        }

        if ($package->runtimeType() === 'docker') {
            $this->assertDockerAvailable($server);
        }

        if ($package->hasInstallOptions()) {
            $options = $this->validateInstallOptions($package, $options);
            Cache::put($this->installOptionsCacheKey($server->id, $slug), $options, 3600);
        } else {
            Cache::forget($this->installOptionsCacheKey($server->id, $slug));
        }

        $displayName = trim((string) ($options['label'] ?? ''));
        if ($displayName === '') {
            $displayName = $package->name();
        }

        $existing = InstalledApplication::query()
            ->where('server_id', $server->id)
            ->where('slug', $slug)
            ->first();

        if ($existing !== null && $existing->status === ApplicationStatus::Installing) {
            throw new InvalidArgumentException('Une installation est déjà en cours pour cette application.');
        }

        $record = InstalledApplication::query()->updateOrCreate(
            ['server_id' => $server->id, 'slug' => $slug],
            [
                'name' => $displayName,
                'version' => $package->version(),
                'category' => $package->category(),
                'status' => ApplicationStatus::Installing,
            ],
        );

        Cache::put($this->progressCacheKey($server->id, $slug), [
            'progress' => 3,
            'message' => 'Mise en file d\'attente…',
            'running' => true,
            'success' => null,
            'slug' => $slug,
            'updated_at' => now()->toIso8601String(),
        ], 3600);

        ApplicationInstallJob::dispatch($record->id, $slug, $server->id, 'install');

        return $record;
    }

    public function queueUninstall(InstalledApplication $application): void
    {
        if ($application->status === ApplicationStatus::Removing) {
            throw new InvalidArgumentException('Une désinstallation est déjà en cours.');
        }

        $application->update(['status' => ApplicationStatus::Removing]);

        Cache::put($this->progressCacheKey($application->server_id, $application->slug), [
            'progress' => 3,
            'message' => 'Désinstallation en file d\'attente…',
            'running' => true,
            'success' => null,
            'slug' => $application->slug,
            'action' => 'uninstall',
            'updated_at' => now()->toIso8601String(),
        ], 3600);

        ApplicationInstallJob::dispatch($application->id, $application->slug, $application->server_id, 'uninstall');
    }

    public function runInstallJob(int $applicationId, string $slug, int $serverId): void
    {
        $record = InstalledApplication::query()->findOrFail($applicationId);
        $server = Server::query()->findOrFail($serverId);
        $package = $this->catalog->find($slug);

        if ($package === null) {
            $this->finishProgress($serverId, $slug, false, 'Package introuvable');

            return;
        }

        $cacheKey = $this->progressCacheKey($serverId, $slug);
        $progressKey = "{$serverId}:{$slug}";

        /** @var array<string, string> $installOptions */
        $installOptions = Cache::get($this->installOptionsCacheKey($serverId, $slug), []);

        $result = $this->runScript($server, $package, 'install', $progressKey, $installOptions);

        Cache::forget($this->installOptionsCacheKey($serverId, $slug));

        if (! $result['success']) {
            $record->update([
                'status' => ApplicationStatus::Error,
                'metadata' => ['error' => $result['output']],
            ]);

            Log::channel('provisioning')->error('Application install failed', [
                'slug' => $slug,
                'server_id' => $serverId,
                'output' => $result['output'],
            ]);

            $this->finishProgress($serverId, $slug, false, 'Échec : '.trim($result['output']));

            return;
        }

        $record->update([
            'status' => ApplicationStatus::Installed,
            'installed_at' => now(),
            'metadata' => $this->buildMetadata($package, $server, $result['output'], $installOptions),
        ]);

        $this->finishProgress($serverId, $slug, true, "« {$package->name()} » installé avec succès.");
    }

    public function runUninstallJob(int $applicationId): void
    {
        $application = InstalledApplication::query()->findOrFail($applicationId);
        $package = $this->catalog->find($application->slug);
        $server = $application->server;
        $progressKey = "{$application->server_id}:{$application->slug}";

        if ($package !== null && is_file($package->uninstallScript())) {
            $result = $this->runScript($server, $package, 'uninstall', $progressKey);

            if (! $result['success']) {
                $application->update([
                    'status' => ApplicationStatus::Error,
                    'metadata' => array_merge($application->metadata ?? [], ['error' => $result['output']]),
                ]);

                $this->finishProgress($application->server_id, $application->slug, false, 'Échec désinstallation : '.trim($result['output']));

                return;
            }
        }

        $name = $application->name;
        $serverId = $application->server_id;
        $slug = $application->slug;
        $application->delete();

        $this->finishProgress($serverId, $slug, true, "« {$name} » désinstallé.");
    }

    /**
     * @return array{success: bool, output: string}
     */
    public function appControl(InstalledApplication $application, string $action): array
    {
        if (! in_array($action, self::APP_ACTIONS, true)) {
            throw new InvalidArgumentException('Action non autorisée.');
        }

        $package = $this->catalog->find($application->slug);

        if ($package === null) {
            return ['success' => false, 'output' => 'Package introuvable'];
        }

        $server = $application->server;

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return ['success' => true, 'output' => "OK:{$action} (dev stub)"];
            }

            if ($package->runtimeType() === 'docker') {
                $result = $this->scripts->run(
                    base_path('agent/scripts/docker-action.sh'),
                    [$package->containerName(), $action],
                );
            } else {
                $service = $package->systemdService() ?? $application->slug;
                $result = $this->scripts->run(
                    base_path('agent/scripts/systemctl-action.sh'),
                    [$action, $service],
                );
            }

            return [
                'success' => $result->successful || str_contains($result->output, 'OK:'),
                'output' => trim($result->output.$result->errorOutput),
            ];
        }

        return $this->remoteAppAction($server, $application->slug, $action);
    }

    public function appRuntimeStatus(InstalledApplication $application): string
    {
        $package = $this->catalog->find($application->slug);

        if ($package === null || $application->status !== ApplicationStatus::Installed) {
            return 'unknown';
        }

        $server = $application->server;

        if (! $this->isLocal($server) || PHP_OS_FAMILY !== 'Linux') {
            return 'unknown';
        }

        if ($package->runtimeType() === 'docker') {
            $result = $this->scripts->run(
                base_path('agent/scripts/docker-status.sh'),
                [$package->containerName()],
            );
            $output = trim($result->output);

            if (str_contains($output, 'STATUS:running')) {
                return 'running';
            }
            if (str_contains($output, 'STATUS:stopped')) {
                return 'stopped';
            }
            if (str_contains($output, 'STATUS:not_found')) {
                return 'not_found';
            }

            return 'unknown';
        }

        $service = $package->systemdService() ?? $application->slug;
        $result = $this->scripts->run(
            base_path('agent/scripts/systemctl-action.sh'),
            ['is-active', $service],
        );

        $output = trim($result->output.$result->errorOutput);

        return $output === 'active' ? 'running' : 'stopped';
    }

    public function appLogs(InstalledApplication $application, int $lines = 100): string
    {
        $package = $this->catalog->find($application->slug);

        if ($package === null) {
            return 'Package introuvable.';
        }

        $server = $application->server;
        $metadata = $application->metadata ?? [];

        if (! $this->isLocal($server) || PHP_OS_FAMILY !== 'Linux') {
            $info = collect([
                'Application' => $application->name,
                'Slug' => $application->slug,
                'Version' => $application->version,
                'Port' => $metadata['port'] ?? '—',
                'URL' => $metadata['url'] ?? '—',
                'Usage' => $metadata['usage'] ?? $package->usageNotes() ?: '—',
            ])->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");

            return $info."\n\n".($metadata['install_output'] ?? 'Aucun log disponible.');
        }

        if ($package->runtimeType() === 'docker') {
            $result = $this->scripts->run(
                base_path('agent/scripts/docker-logs.sh'),
                [$package->containerName(), (string) $lines],
            );

            return trim($result->output.$result->errorOutput) ?: 'Aucun log.';
        }

        $service = $package->systemdService() ?? $application->slug;
        $result = $this->scripts->run(
            base_path('agent/scripts/systemctl-logs.sh'),
            [$service, (string) $lines],
        );

        return trim($result->output.$result->errorOutput) ?: 'Aucun log.';
    }

    /**
     * @return array<string, mixed>
     */
    public function appInfo(InstalledApplication $application): array
    {
        $package = $this->catalog->find($application->slug);
        $metadata = $application->metadata ?? [];
        $host = $this->accessHost->resolve($application->server);

        return [
            'name' => $application->name,
            'slug' => $application->slug,
            'version' => $application->version,
            'status' => $application->status->value,
            'runtime_status' => $this->appRuntimeStatus($application),
            'runtime_type' => $metadata['runtime_type'] ?? $package?->runtimeType() ?? 'docker',
            'container' => $metadata['container'] ?? $package?->containerName(),
            'port' => $metadata['port'] ?? $package?->port(),
            'url' => $package?->accessUrl($host) ?? $metadata['url'] ?? null,
            'usage' => $metadata['usage'] ?? $package?->usageNotes() ?? '',
            'username' => $metadata['credentials']['username'] ?? null,
            'installed_at' => $application->installed_at?->format('d/m/Y H:i'),
            'install_output' => $metadata['install_output'] ?? '',
        ];
    }

    public function install(string $slug, ?Server $server = null): InstalledApplication
    {
        return $this->queueInstall($slug, $server);
    }

    public function uninstall(InstalledApplication $application): void
    {
        $this->queueUninstall($application);
    }

    /**
     * @param  array<string, string>  $installOptions
     * @return array{success: bool, output: string}
     */
    private function runScript(Server $server, ApplicationPackage $package, string $action, ?string $progressKey = null, array $installOptions = []): array
    {
        $script = $action === 'install' ? $package->installScript() : $package->uninstallScript();

        if (! is_file($script)) {
            return ['success' => false, 'output' => "Script {$action} introuvable"];
        }

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return ['success' => true, 'output' => "OK:{$package->slug} (dev stub)"];
            }

            $wrapper = base_path('agent/scripts/marketplace-exec.sh');
            $args = [$script];

            if ($progressKey !== null) {
                $args[] = $progressKey;
                $args[] = $action === 'install' ? 'installation' : 'désinstallation';
            }

            $env = $this->installOptionsToEnv($installOptions);

            $result = $this->scripts->run($wrapper, $args, 900, $env);

            return [
                'success' => $result->successful || str_contains($result->output, 'OK:'),
                'output' => trim($result->output.$result->errorOutput),
            ];
        }

        return $this->remoteRun($server, $package->slug, $action);
    }

    /**
     * @param  array<string, string>  $installOptions
     * @return array<string, mixed>
     */
    private function buildMetadata(ApplicationPackage $package, Server $server, string $output, array $installOptions = []): array
    {
        $host = $this->accessHost->resolve($server);
        $port = $package->port();

        if (preg_match('/port (\d+)/i', $output, $matches)) {
            $port = (int) $matches[1];
        }

        $url = $package->accessUrl($host);
        if ($url === null && $port !== null) {
            $url = "http://{$host}:{$port}";
        }

        $username = trim((string) ($installOptions['username'] ?? ''));
        if ($username === '' && preg_match('/credentials:([^\s\/]+)/', $output, $credMatch)) {
            $username = $credMatch[1];
        }

        $usage = $package->usageNotes();
        if ($username !== '') {
            $usage = preg_replace(
                '/Identifiants par défaut\s*:\s*[^\n.]*/iu',
                "Identifiant : {$username} (mot de passe défini à l'installation)",
                $usage,
            ) ?? $usage;
        }

        $metadata = [
            'runtime_type' => $package->runtimeType(),
            'container' => $package->containerName(),
            'service' => $package->systemdService(),
            'port' => $port,
            'url' => $url,
            'usage' => $usage,
            'install_output' => trim($output),
        ];

        if ($username !== '') {
            $metadata['credentials'] = ['username' => $username];
        }

        return $metadata;
    }

    /**
     * @param  array<string, string>  $installOptions
     * @return array<string, string>
     */
    private function installOptionsToEnv(array $installOptions): array
    {
        $env = [];

        foreach ($installOptions as $key => $value) {
            if ($value === '' || str_ends_with($key, '_confirm') || str_ends_with($key, 'pass_confirm')) {
                continue;
            }

            $envKey = 'OBIORA_APP_'.strtoupper(preg_replace('/[^a-z0-9_]/', '_', $key) ?? $key);
            $env[$envKey] = $value;
        }

        return $env;
    }

    private function finishProgress(int $serverId, string $slug, bool $success, string $message): void
    {
        Cache::put($this->progressCacheKey($serverId, $slug), [
            'progress' => 100,
            'message' => $message,
            'running' => false,
            'success' => $success,
            'slug' => $slug,
            'updated_at' => now()->toIso8601String(),
        ], 3600);
    }

    private function isLocal(Server $server): bool
    {
        return $server->is_master || $server->type->value === 'local';
    }

    private function assertDockerAvailable(Server $server): void
    {
        if (! $this->isLocal($server) || PHP_OS_FAMILY !== 'Linux') {
            return;
        }

        $result = $this->scripts->run(base_path('agent/scripts/docker-info.sh'));

        if (! $result->successful || ! str_starts_with(trim($result->output), 'OK:')) {
            throw new InvalidArgumentException(
                'Docker est requis pour installer cette application. Allez dans le menu Docker et installez-le d\'abord.'
            );
        }
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function remoteRun(Server $server, string $slug, string $action): array
    {
        $node = $server->primaryNode;

        if ($node === null) {
            return ['success' => false, 'output' => 'Nœud agent introuvable'];
        }

        try {
            $host = $node->host ?? $server->ip_address;
            $port = $node->port ?? 9100;

            $response = Http::timeout(600)
                ->withToken($server->agent_token)
                ->post("http://{$host}:{$port}/api/v1/applications/{$action}", [
                    'slug' => $slug,
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
     * @return array{success: bool, output: string}
     */
    private function remoteAppAction(Server $server, string $slug, string $action): array
    {
        $node = $server->primaryNode;

        if ($node === null) {
            return ['success' => false, 'output' => 'Nœud agent introuvable'];
        }

        try {
            $host = $node->host ?? $server->ip_address;
            $port = $node->port ?? 9100;

            $response = Http::timeout(120)
                ->withToken($server->agent_token)
                ->post("http://{$host}:{$port}/api/v1/applications/{$slug}/{$action}");
        } catch (\Throwable $e) {
            return ['success' => false, 'output' => $e->getMessage()];
        }

        return [
            'success' => (bool) $response->json('success', false),
            'output' => (string) $response->json('output', $response->body()),
        ];
    }
}

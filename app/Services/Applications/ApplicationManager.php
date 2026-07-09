<?php

declare(strict_types=1);

namespace App\Services\Applications;

use App\DTOs\ApplicationPackage;
use App\Enums\ApplicationStatus;
use App\Events\ProgressUpdated;
use App\Jobs\ApplicationInstallJob;
use App\Models\InstalledApplication;
use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\Database\DatabaseProvisioner;
use App\Support\Realtime;
use App\Support\ServerAccessHost;
use App\Services\System\PrivilegedScriptRunner;
use Illuminate\Support\Carbon;
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
        private readonly DatabaseProvisioner $databaseProvisioner,
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
            $isConfirmField = isset($field['matches']);

            if ($required && $value === '') {
                $errors[] = "« {$label} » est requis.";

                continue;
            }

            $min = isset($field['min']) ? (int) $field['min'] : null;
            if (! $isConfirmField && $min !== null && $value !== '' && mb_strlen($value) < $min) {
                $errors[] = "« {$label} » doit contenir au moins {$min} caractères.";
            }

            if (($field['type'] ?? '') === 'password' && isset($field['confirm'])) {
                $confirmName = (string) $field['confirm'];
                if ($value !== trim((string) ($options[$confirmName] ?? ''))) {
                    $errors[] = 'Les mots de passe ne correspondent pas.';
                }
            }

            if ($isConfirmField) {
                $matchName = (string) $field['matches'];
                $matchValue = trim((string) ($options[$matchName] ?? ''));
                if ($value !== '' && $value !== $matchValue) {
                    $errors[] = 'Les mots de passe ne correspondent pas.';
                }

                continue;
            }

            $validated[$name] = $value;
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

        if (! $package->isInstallable()) {
            throw new InvalidArgumentException($package->installNotice() !== ''
                ? $package->installNotice()
                : "« {$package->name()} » n'est pas installable depuis le marketplace.");
        }

        if ($this->isInstalled($slug, $server)) {
            throw new InvalidArgumentException("« {$package->name()} » est déjà installé sur ce serveur.");
        }

        if ($package->effectiveRuntimeType() === 'docker') {
            $this->assertDockerAvailable($server);
        }

        if ($package->hasInstallOptions()) {
            $options = $this->validateInstallOptions($package, $options);
        }

        if ($package->databaseAutoProvision()) {
            $options = $this->provisionDatabaseForPackage($package, $server, $options);
        }

        if ($package->hasInstallOptions() || $package->databaseAutoProvision()) {
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

        $this->storeProgress($server->id, $slug, [
            'progress' => 3,
            'message' => 'Mise en file d\'attente…',
            'running' => true,
            'success' => null,
            'slug' => $slug,
            'updated_at' => now()->toIso8601String(),
        ]);

        ApplicationInstallJob::dispatch($record->id, $slug, $server->id, 'install');

        return $record;
    }

    public function queueUninstall(InstalledApplication $application): void
    {
        if ($application->status === ApplicationStatus::Removing) {
            throw new InvalidArgumentException('Une désinstallation est déjà en cours.');
        }

        $application->update(['status' => ApplicationStatus::Removing]);

        $this->storeProgress($application->server_id, $application->slug, [
            'progress' => 3,
            'message' => 'Désinstallation en file d\'attente…',
            'running' => true,
            'success' => null,
            'slug' => $application->slug,
            'action' => 'uninstall',
            'updated_at' => now()->toIso8601String(),
        ]);

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

            $this->finishProgress($serverId, $slug, false, 'Échec : '.trim($result['output']), trim($result['output']));

            return;
        }

        $metadata = $this->buildMetadata($package, $server, $result['output'], $installOptions);

        $record->update([
            'status' => ApplicationStatus::Installed,
            'installed_at' => now(),
            'metadata' => $metadata,
        ]);

        $this->syncRuntimeMetadata($record, $package);
        $record->refresh();

        $runtimeStatus = $this->appRuntimeStatus($record);
        $runtimeType = $package->effectiveRuntimeType();
        $finishMessage = "« {$package->name()} » installé avec succès.";

        if (
            in_array($runtimeStatus, ['stopped', 'not_found', 'unknown'], true)
            && in_array($runtimeType, ['docker', 'systemd'], true)
        ) {
            $record->update([
                'metadata' => array_merge($record->metadata ?? [], [
                    'runtime_warning' => 'Service ou conteneur inactif après installation. Utilisez Démarrer ou consultez les logs.',
                    'runtime_status_at_install' => $runtimeStatus,
                ]),
            ]);
            $finishMessage = "« {$package->name()} » installé — statut runtime : {$runtimeStatus}.";
        }

        $this->finishProgress($serverId, $slug, true, $finishMessage);
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

                $this->finishProgress($application->server_id, $application->slug, false, 'Échec désinstallation : '.trim($result['output']), trim($result['output']));

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

            $runtimeType = $package->effectiveRuntimeType();

            if ($runtimeType === 'docker') {
                $result = $this->scripts->run(
                    base_path('agent/scripts/docker-action.sh'),
                    [$package->effectiveContainerName(), $action],
                );
            } elseif ($runtimeType === 'systemd') {
                $service = $package->effectiveSystemdService() ?? $application->slug;
                $result = $this->scripts->run(
                    base_path('agent/scripts/systemctl-action.sh'),
                    [$action, $service],
                );
            } else {
                return ['success' => false, 'output' => 'Cette application ne supporte pas start/stop/restart.'];
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
            return $this->remoteAppRuntimeStatus($server, $application, $package);
        }

        $runtimeType = $package->effectiveRuntimeType();

        if ($runtimeType === 'docker') {
            $result = $this->scripts->run(
                base_path('agent/scripts/docker-status.sh'),
                [$package->effectiveContainerName()],
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

        if ($runtimeType !== 'systemd') {
            return 'installed';
        }

        $service = $package->effectiveSystemdService() ?? $application->slug;
        $result = $this->scripts->run(
            base_path('agent/scripts/systemctl-action.sh'),
            ['is-active', $service],
        );

        $output = trim($result->output.$result->errorOutput);

        if (preg_match('/\bactive\b/', $output)) {
            return 'running';
        }

        if (preg_match('/\b(inactive|failed|dead)\b/', $output)) {
            return 'stopped';
        }

        return 'unknown';
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
            if (! $this->isLocal($server) && $server->primaryNode !== null) {
                return $this->remoteAppLogs($server, $application, $package, $lines);
            }

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

        $runtimeType = $package->effectiveRuntimeType();

        if ($runtimeType === 'docker') {
            $result = $this->scripts->run(
                base_path('agent/scripts/docker-logs.sh'),
                [$package->effectiveContainerName(), (string) $lines],
            );

            return trim($result->output.$result->errorOutput) ?: 'Aucun log.';
        }

        if ($runtimeType !== 'systemd') {
            return trim((string) ($metadata['install_output'] ?? '')) ?: 'Aucun log runtime pour cette application.';
        }

        $service = $package->effectiveSystemdService() ?? $application->slug;
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

        if ($package !== null) {
            $this->syncRuntimeMetadata($application, $package);
            $application->refresh();
        }

        $metadata = $application->metadata ?? [];
        $host = $this->accessHost->resolve($application->server);
        $runtimeType = $package?->effectiveRuntimeType() ?? $metadata['runtime_type'] ?? 'docker';
        $runtimeTarget = $runtimeType === 'docker'
            ? ($package?->effectiveContainerName() ?? $metadata['container'] ?? 'obiora-'.$application->slug)
            : ($package?->effectiveSystemdService() ?? $metadata['service'] ?? $application->slug);

        return [
            'name' => $application->name,
            'slug' => $application->slug,
            'version' => $application->version,
            'status' => $application->status->value,
            'runtime_status' => $this->appRuntimeStatus($application),
            'runtime_type' => $runtimeType,
            'container' => $runtimeTarget,
            'port' => $metadata['port'] ?? $package?->port(),
            'url' => $package?->accessUrl($host) ?? $metadata['url'] ?? null,
            'usage' => $metadata['usage'] ?? $package?->usageNotes() ?? '',
            'runtime_warning' => $metadata['runtime_warning'] ?? null,
            'username' => $metadata['credentials']['username'] ?? null,
            'password' => $metadata['credentials']['password'] ?? null,
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
            if ($env !== []) {
                $args[] = '__obiora_env';
                $args[] = (string) count($env);
                foreach ($env as $key => $value) {
                    $args[] = $key.'='.base64_encode($value);
                }
            }

            $result = $this->scripts->run($wrapper, $args, 900);

            return [
                'success' => $result->successful || str_contains($result->output, 'OK:'),
                'output' => trim($result->output.$result->errorOutput),
            ];
        }

        return $this->remoteRun($server, $package->slug, $action, $installOptions);
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
        if ($username === '') {
            $username = trim((string) ($installOptions['domain'] ?? ''));
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
            'runtime_type' => $package->effectiveRuntimeType(),
            'container' => $package->effectiveRuntimeType() === 'docker' ? $package->effectiveContainerName() : null,
            'service' => $package->effectiveSystemdService(),
            'port' => $port,
            'url' => $url,
            'usage' => $usage,
            'install_output' => trim($output),
        ];

        $password = trim((string) ($installOptions['pass'] ?? $installOptions['token'] ?? ''));
        if ($username !== '' || $password !== '') {
            $metadata['credentials'] = array_filter([
                'username' => $username !== '' ? $username : null,
                'password' => $password !== '' ? $password : null,
            ], static fn (?string $value): bool => $value !== null && $value !== '');
        }

        $dbHost = trim((string) ($installOptions['db_host'] ?? ''));
        if ($dbHost !== '') {
            $metadata['database'] = [
                'host' => $dbHost,
                'name' => $installOptions['db_name'] ?? '',
                'user' => $installOptions['db_user'] ?? '',
                'password' => $installOptions['db_pass'] ?? '',
            ];
            $dbBlock = sprintf(
                "\n\nBase de données (créée automatiquement) :\n• Hôte : %s\n• Base : %s\n• Utilisateur : %s\n• Mot de passe : %s",
                $dbHost,
                $installOptions['db_name'] ?? '—',
                $installOptions['db_user'] ?? '—',
                $installOptions['db_pass'] ?? '—',
            );
            $metadata['usage'] = trim(($metadata['usage'] ?? '').$dbBlock);
        }

        return $metadata;
    }

    /**
     * @param  array<string, string>  $installOptions
     * @return array<string, string>
     */
    /**
     * @param  array<string, string>  $installOptions
     * @return array<string, string>
     */
    public function encodeRemoteInstallEnv(array $installOptions): array
    {
        $env = $this->installOptionsToEnv($installOptions);
        $encoded = [];

        foreach ($env as $key => $value) {
            $encoded[$key] = base64_encode($value);
        }

        return $encoded;
    }

    private function installOptionsToEnv(array $installOptions): array
    {
        $env = [];

        foreach ($installOptions as $key => $value) {
            if ($value === '' || str_ends_with($key, '_confirm') || str_ends_with($key, 'pass_confirm') || $key === 'pass2') {
                continue;
            }

            $envKey = 'OBIORA_APP_'.strtoupper(preg_replace('/[^a-z0-9_]/', '_', $key) ?? $key);
            $env[$envKey] = $value;
        }

        return $env;
    }

    public function installProgressStatus(int $serverId, string $slug): ?array
    {
        $status = Cache::get($this->progressCacheKey($serverId, $slug));

        return is_array($status) ? $status : null;
    }

    public function recoverStaleMarketplaceProgress(int $serverId, int $maxAgeMinutes = 20): void
    {
        $apps = InstalledApplication::query()
            ->where('server_id', $serverId)
            ->whereIn('status', [ApplicationStatus::Installing, ApplicationStatus::Removing])
            ->get();

        foreach ($apps as $app) {
            $status = $this->installProgressStatus($serverId, $app->slug);

            if (! is_array($status)) {
                $this->resetStuckApplication($app);

                continue;
            }

            $updatedAt = isset($status['updated_at'])
                ? Carbon::parse((string) $status['updated_at'])
                : null;

            $isStale = ($status['running'] ?? false)
                && ($updatedAt === null || $updatedAt->lt(now()->subMinutes($maxAgeMinutes)));

            if ($isStale) {
                $this->finishProgress(
                    $serverId,
                    $app->slug,
                    false,
                    'Opération expirée — vérifiez que obiora-queue est actif.',
                );
                $this->resetStuckApplication($app);
            }
        }
    }

    private function resetStuckApplication(InstalledApplication $application): void
    {
        if ($application->status === ApplicationStatus::Removing) {
            $application->update(['status' => ApplicationStatus::Installed]);

            return;
        }

        if ($application->status === ApplicationStatus::Installing) {
            $application->update([
                'status' => ApplicationStatus::Error,
                'metadata' => array_merge($application->metadata ?? [], [
                    'error' => 'Installation interrompue ou file d\'attente inactive.',
                ]),
            ]);
        }
    }

    private function finishProgress(int $serverId, string $slug, bool $success, string $message, string $log = ''): void
    {
        $this->storeProgress($serverId, $slug, [
            'progress' => 100,
            'message' => $message,
            'log' => $log !== '' ? $log : $message,
            'running' => false,
            'success' => $success,
            'slug' => $slug,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeProgress(int $serverId, string $slug, array $payload): void
    {
        Cache::put($this->progressCacheKey($serverId, $slug), $payload, 3600);

        if (Realtime::enabled()) {
            event(new ProgressUpdated($serverId, 'marketplace', $slug, $payload));
        }
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    private function provisionDatabaseForPackage(ApplicationPackage $package, Server $server, array $options): array
    {
        $dbName = preg_replace('/[^a-z0-9_]/', '_', strtolower($package->databaseNamePrefix())) ?? $package->slug;
        $result = $this->databaseProvisioner->create($server, $dbName);

        if (! $result['success']) {
            throw new InvalidArgumentException('Échec création base de données : '.trim($result['output']));
        }

        $options['db_name'] = $dbName;
        $options['db_user'] = $result['username'];
        $options['db_pass'] = $result['password'];
        $options['db_host'] = $result['docker_host'];

        return $options;
    }

    private function syncRuntimeMetadata(InstalledApplication $application, ApplicationPackage $package): void
    {
        $host = $this->accessHost->resolve($application->server);
        $runtimeType = $package->effectiveRuntimeType();
        $resolved = array_filter([
            'runtime_type' => $runtimeType,
            'service' => $runtimeType === 'systemd' ? $package->effectiveSystemdService() : null,
            'container' => $runtimeType === 'docker' ? $package->effectiveContainerName() : null,
            'port' => $package->port(),
            'url' => $package->accessUrl($host),
        ], static fn ($value): bool => $value !== null && $value !== '');

        $metadata = $application->metadata ?? [];
        $changed = false;

        foreach ($resolved as $key => $value) {
            if (($metadata[$key] ?? null) !== $value) {
                $changed = true;
                break;
            }
        }

        if (! $changed) {
            return;
        }

        $application->update([
            'metadata' => array_merge($metadata, $resolved),
        ]);
    }

    private function isLocal(Server $server): bool
    {
        return $server->is_master || $server->type->value === 'local';
    }

    private function assertDockerAvailable(Server $server): void
    {
        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return;
            }

            $result = $this->scripts->run(base_path('agent/scripts/docker-info.sh'));

            if (! $result->successful || ! str_starts_with(trim($result->output), 'OK:')) {
                throw new InvalidArgumentException(
                    'Docker est requis pour installer cette application. Allez dans le menu Docker et installez-le d\'abord.'
                );
            }

            return;
        }

        $node = $server->primaryNode;

        if ($node === null) {
            throw new InvalidArgumentException('Agent slave non configuré — impossible de vérifier Docker.');
        }

        try {
            $host = $node->host ?? $server->ip_address;
            $port = $node->port ?? 9100;

            $response = Http::timeout(15)
                ->withToken($server->agent_token)
                ->get("http://{$host}:{$port}/api/v1/docker/info");

            if (! $response->successful() || ! (bool) $response->json('data.installed', false)) {
                throw new InvalidArgumentException(
                    'Docker est requis sur ce serveur distant. Allez dans le menu Docker (avec ce serveur sélectionné) et installez-le d\'abord.'
                );
            }
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                'Impossible de vérifier Docker sur le serveur distant : '.$e->getMessage()
            );
        }
    }

    /**
     * @param  array<string, string>  $installOptions
     * @return array{success: bool, output: string}
     */
    private function remoteRun(Server $server, string $slug, string $action, array $installOptions = []): array
    {
        $node = $server->primaryNode;

        if ($node === null) {
            return ['success' => false, 'output' => 'Nœud agent introuvable'];
        }

        try {
            $host = $node->host ?? $server->ip_address;
            $port = $node->port ?? 9100;
            $payload = ['slug' => $slug];
            $env = $this->encodeRemoteInstallEnv($installOptions);

            if ($env !== []) {
                $payload['env'] = $env;
            }

            $response = Http::timeout(600)
                ->withToken($server->agent_token)
                ->post("http://{$host}:{$port}/api/v1/applications/{$action}", $payload);
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
                ->post("http://{$host}:{$port}/api/v1/applications/control", [
                    'slug' => $slug,
                    'action' => $action,
                    'runtime_type' => $this->catalog->find($slug)?->effectiveRuntimeType() ?? 'docker',
                    'target' => $this->resolveRemoteTarget($slug),
                ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'output' => $e->getMessage()];
        }

        return [
            'success' => (bool) $response->json('success', false),
            'output' => (string) $response->json('output', $response->body()),
        ];
    }

    private function resolveRemoteTarget(string $slug): string
    {
        $package = $this->catalog->find($slug);

        if ($package === null) {
            return 'obiora-'.$slug;
        }

        if ($package->effectiveRuntimeType() === 'docker') {
            return $package->effectiveContainerName();
        }

        return $package->effectiveSystemdService() ?? $slug;
    }

    private function remoteAppRuntimeStatus(Server $server, InstalledApplication $application, ApplicationPackage $package): string
    {
        $node = $server->primaryNode;

        if ($node === null) {
            return 'unknown';
        }

        if (! in_array($package->effectiveRuntimeType(), ['docker', 'systemd'], true)) {
            return 'installed';
        }

        try {
            $host = $node->host ?? $server->ip_address;
            $port = $node->port ?? 9100;

            $response = Http::timeout(30)
                ->withToken($server->agent_token)
                ->post("http://{$host}:{$port}/api/v1/applications/status", [
                    'slug' => $application->slug,
                    'runtime_type' => $package->effectiveRuntimeType(),
                    'target' => $this->resolveRemoteTarget($application->slug),
                ]);
        } catch (\Throwable) {
            return 'unknown';
        }

        return (string) $response->json('status', 'unknown');
    }

    private function remoteAppLogs(Server $server, InstalledApplication $application, ApplicationPackage $package, int $lines): string
    {
        $node = $server->primaryNode;

        if ($node === null) {
            return 'Nœud agent introuvable.';
        }

        try {
            $host = $node->host ?? $server->ip_address;
            $port = $node->port ?? 9100;

            $response = Http::timeout(60)
                ->withToken($server->agent_token)
                ->post("http://{$host}:{$port}/api/v1/applications/logs", [
                    'slug' => $application->slug,
                    'runtime_type' => $package->effectiveRuntimeType(),
                    'target' => $this->resolveRemoteTarget($application->slug),
                    'lines' => $lines,
                ]);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return (string) $response->json('output', $response->body());
    }
}

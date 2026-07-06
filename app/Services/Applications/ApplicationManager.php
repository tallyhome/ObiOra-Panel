<?php

declare(strict_types=1);

namespace App\Services\Applications;

use App\DTOs\ApplicationPackage;
use App\Enums\ApplicationStatus;
use App\Models\InstalledApplication;
use App\Models\Server;
use App\Services\Core\ServerManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class ApplicationManager
{
    public function __construct(
        private readonly ServerManager $serverManager,
        private readonly ApplicationCatalog $catalog,
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

    public function install(string $slug, ?Server $server = null): InstalledApplication
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

        $record = InstalledApplication::query()->updateOrCreate(
            ['server_id' => $server->id, 'slug' => $slug],
            [
                'name' => $package->name(),
                'version' => $package->version(),
                'category' => $package->category(),
                'status' => ApplicationStatus::Installing,
            ],
        );

        $result = $this->runScript($server, $package, 'install');

        if (! $result['success']) {
            $record->update([
                'status' => ApplicationStatus::Error,
                'metadata' => ['error' => $result['output']],
            ]);

            Log::channel('provisioning')->error('Application install failed', [
                'slug' => $slug,
                'server_id' => $server->id,
                'output' => $result['output'],
            ]);

            throw new InvalidArgumentException('Échec installation : '.trim($result['output']));
        }

        $record->update([
            'status' => ApplicationStatus::Installed,
            'installed_at' => now(),
            'metadata' => ['output' => $result['output']],
        ]);

        return $record->fresh() ?? $record;
    }

    public function uninstall(InstalledApplication $application): void
    {
        $package = $this->catalog->find($application->slug);
        $server = $application->server;

        $application->update(['status' => ApplicationStatus::Removing]);

        if ($package !== null && is_file($package->uninstallScript())) {
            $result = $this->runScript($server, $package, 'uninstall');

            if (! $result['success']) {
                $application->update([
                    'status' => ApplicationStatus::Error,
                    'metadata' => ['error' => $result['output']],
                ]);

                throw new InvalidArgumentException('Échec désinstallation : '.trim($result['output']));
            }
        }

        $application->delete();
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function runScript(Server $server, ApplicationPackage $package, string $action): array
    {
        $script = $action === 'install' ? $package->installScript() : $package->uninstallScript();

        if (! is_file($script)) {
            return ['success' => false, 'output' => "Script {$action} introuvable"];
        }

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return ['success' => true, 'output' => "OK:{$package->slug} (dev stub)"];
            }

            $command = $this->buildCommand($script);

            $result = $this->serverManager->executorFor($server)->run($command, ['timeout' => 600]);

            return [
                'success' => $result->successful || str_contains($result->output, 'OK:'),
                'output' => trim($result->output.$result->errorOutput),
            ];
        }

        return $this->remoteRun($server, $package->slug, $action);
    }

    private function buildCommand(string $script): string
    {
        $command = 'bash '.escapeshellarg($script);

        if (PHP_OS_FAMILY === 'Linux' && (! function_exists('posix_geteuid') || posix_geteuid() !== 0)) {
            $command = 'sudo -n '.$command;
        }

        return $command;
    }

    private function isLocal(Server $server): bool
    {
        return $server->is_master || $server->type->value === 'local';
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
}

<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\Server;
use App\Services\Core\ServerManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class BackupManager
{
    public function __construct(
        private readonly ServerManager $serverManager,
        private readonly BackupProvisioner $provisioner,
    ) {}

    /**
     * @return Collection<int, Backup>
     */
    public function forServer(?Server $server = null): Collection
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return collect();
        }

        return Backup::query()
            ->where('server_id', $server->id)
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param  array{name: string, type: string, target?: string|null}  $data
     */
    public function create(array $data, ?Server $server = null): Backup
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            throw new InvalidArgumentException('Aucun serveur sélectionné.');
        }

        $type = BackupType::from($data['type']);
        $name = trim($data['name']);
        $target = $data['target'] ?? null;

        $backup = Backup::query()->create([
            'server_id' => $server->id,
            'name' => $name,
            'type' => $type,
            'filename' => '',
            'storage_path' => '',
            'target' => $target,
            'status' => BackupStatus::Pending,
        ]);

        $result = $this->provisioner->create($server, $name, $type, $target);

        if (! $result['success']) {
            $backup->update([
                'status' => BackupStatus::Error,
                'metadata' => ['error' => $result['output']],
            ]);

            Log::channel('provisioning')->error('Backup failed', [
                'name' => $name,
                'server_id' => $server->id,
                'output' => $result['output'],
            ]);

            throw new InvalidArgumentException('Échec sauvegarde : '.trim($result['output']));
        }

        $backup->update([
            'filename' => $result['filename'],
            'storage_path' => $result['storage_path'],
            'size_bytes' => $result['size_bytes'],
            'status' => BackupStatus::Completed,
            'completed_at' => now(),
        ]);

        return $backup->fresh() ?? $backup;
    }

    public function delete(Backup $backup): void
    {
        if ($backup->filename !== '') {
            $result = $this->provisioner->delete($backup->server, $backup->filename);

            if (! $result['success']) {
                throw new InvalidArgumentException('Échec suppression : '.trim($result['output']));
            }
        }

        $backup->delete();
    }

    public function restore(Backup $backup, ?string $database = null): string
    {
        if ($backup->type !== BackupType::Database) {
            throw new InvalidArgumentException('Restauration disponible uniquement pour les sauvegardes base de données.');
        }

        if ($backup->filename === '') {
            throw new InvalidArgumentException('Fichier de sauvegarde introuvable.');
        }

        $result = $this->provisioner->restore(
            $backup->server,
            $backup->filename,
            $database ?? $backup->target,
        );

        if (! $result['success']) {
            throw new InvalidArgumentException('Échec restauration : '.trim($result['output']));
        }

        return $result['database'];
    }
}

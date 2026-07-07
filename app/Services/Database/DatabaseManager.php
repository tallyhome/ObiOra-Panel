<?php

declare(strict_types=1);

namespace App\Services\Database;

use App\Enums\DatabaseStatus;
use App\Models\ManagedDatabase;
use App\Models\Server;
use App\Services\Core\ServerManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class DatabaseManager
{
    public function __construct(
        private readonly ServerManager $serverManager,
        private readonly DatabaseProvisioner $provisioner,
    ) {}

    /**
     * @return Collection<int, ManagedDatabase>
     */
    public function forServer(?Server $server = null): Collection
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return collect();
        }

        return ManagedDatabase::query()
            ->where('server_id', $server->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function listOnServer(?Server $server = null): array
    {
        return $this->provisioner->listOnServer($server ?? $this->serverManager->getCurrentServer());
    }

    /**
     * @param  array{name: string, username?: string|null, password?: string|null}  $data
     */
    public function create(array $data, ?Server $server = null): ManagedDatabase
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            throw new InvalidArgumentException('Aucun serveur sélectionné.');
        }

        $name = strtolower(trim($data['name']));

        $this->clearStaleDatabaseRecord($server->id, $name);

        if (ManagedDatabase::query()->where('server_id', $server->id)->where('name', $name)->exists()) {
            throw new InvalidArgumentException("La base « {$name} » existe déjà sur ce serveur.");
        }

        $record = ManagedDatabase::query()->create([
            'server_id' => $server->id,
            'name' => $name,
            'username' => '',
            'password' => 'pending',
            'host' => (string) config('obiora.databases.default_host', 'localhost'),
            'charset' => (string) config('obiora.databases.default_charset', 'utf8mb4'),
            'collation' => (string) config('obiora.databases.default_collation', 'utf8mb4_unicode_ci'),
            'status' => DatabaseStatus::Pending,
        ]);

        $result = $this->provisioner->create(
            $server,
            $name,
            $data['username'] ?? null,
            $data['password'] ?? null,
        );

        if (! $result['success']) {
            $record->update([
                'status' => DatabaseStatus::Error,
                'metadata' => ['error' => $result['output']],
            ]);

            Log::channel('provisioning')->error('Database creation failed', [
                'name' => $name,
                'server_id' => $server->id,
                'output' => $result['output'],
            ]);

            throw new InvalidArgumentException('Échec création base : '.trim($result['output']));
        }

        $record->update([
            'username' => $result['username'],
            'password' => $result['password'],
            'status' => DatabaseStatus::Active,
        ]);

        return $record->fresh() ?? $record;
    }

    public function delete(ManagedDatabase $database): void
    {
        $result = $this->provisioner->delete(
            $database->server,
            $database->name,
            $database->username,
        );

        if (! $result['success']) {
            if (in_array($database->status, [DatabaseStatus::Error, DatabaseStatus::Pending], true)) {
                $database->delete();

                return;
            }

            throw new InvalidArgumentException('Échec suppression : '.trim($result['output']));
        }

        $database->delete();
    }

    private function clearStaleDatabaseRecord(int $serverId, string $name): void
    {
        ManagedDatabase::query()
            ->where('server_id', $serverId)
            ->where('name', $name)
            ->whereIn('status', [DatabaseStatus::Error, DatabaseStatus::Pending])
            ->delete();
    }
}

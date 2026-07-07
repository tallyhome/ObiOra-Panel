<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Docker\DockerManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class DockerUninstallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(DockerManager $dockerManager): void
    {
        Cache::put('obiora_progress:docker_uninstall', [
            'progress' => 3,
            'message' => 'Démarrage de la désinstallation Docker…',
            'running' => true,
            'success' => null,
            'updated_at' => now()->toIso8601String(),
        ], 3600);

        $result = $dockerManager->runUninstallScript();

        Cache::put('obiora_progress:docker_uninstall', [
            'progress' => 100,
            'message' => $result['message'],
            'running' => false,
            'success' => $result['success'],
            'updated_at' => now()->toIso8601String(),
        ], 3600);
    }
}

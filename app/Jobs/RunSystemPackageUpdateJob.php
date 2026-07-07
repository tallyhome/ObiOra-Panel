<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Core\SystemMaintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class RunSystemPackageUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function handle(SystemMaintenance $maintenance): void
    {
        Cache::put('obiora:system_update', [
            'running' => true,
            'message' => 'Mise à jour des paquets en cours…',
            'output' => '',
            'success' => null,
            'updated_at' => now()->toIso8601String(),
        ], 7200);

        $result = $maintenance->runPackageUpdate();

        Cache::put('obiora:system_update', [
            'running' => false,
            'message' => $result['message'],
            'output' => $result['output'],
            'success' => $result['success'],
            'updated_at' => now()->toIso8601String(),
        ], 7200);
    }
}

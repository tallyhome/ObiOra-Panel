<?php

declare(strict_types=1);

namespace App\Jobs\Diagnostics;

use App\Services\Diagnostics\DoctorDeployProgressService;
use App\Services\Diagnostics\DoctorDeployRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DoctorRemoteDeployJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly int $serverId,
        public readonly string $sshHost,
        public readonly int $sshPort,
        public readonly string $sshUser,
        public readonly bool $installDoctor = true,
        public readonly bool $installCrashAnalyzer = true,
        public readonly bool $installCrashHunter = true,
        public readonly bool $installSlave = false,
    ) {}

    public function handle(DoctorDeployRunner $runner): void
    {
        $runner->run(
            $this->serverId,
            $this->sshHost,
            $this->sshPort,
            $this->sshUser,
            $this->installDoctor,
            $this->installCrashAnalyzer,
            $this->installCrashHunter,
            $this->installSlave,
        );
    }

    public function failed(\Throwable $exception): void
    {
        app(DoctorDeployProgressService::class)->finish(
            $this->serverId,
            false,
            'Erreur interne : '.$exception->getMessage(),
            [],
            $exception->getMessage(),
        );
    }
}

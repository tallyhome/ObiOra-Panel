<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\SystemExecutorInterface;
use App\Services\System\PrivilegedScriptRunner;
use Illuminate\Support\Facades\File;

final class SystemMaintenance
{
    public function __construct(
        private readonly PrivilegedScriptRunner $scripts,
        private readonly SystemExecutorInterface $executor,
    ) {}

    public function canManage(): bool
    {
        return PHP_OS_FAMILY === 'Linux'
            && is_file($this->packageUpdateScript())
            && is_file($this->rebootScript());
    }

    /**
     * @return array{success: bool, message: string, output: string}
     */
    public function runPackageUpdate(): array
    {
        if (! $this->canManage()) {
            return [
                'success' => false,
                'message' => 'Mise à jour système indisponible sur cette plateforme.',
                'output' => '',
            ];
        }

        $result = $this->scripts->run($this->packageUpdateScript(), [], 3600);

        return [
            'success' => $result->successful,
            'message' => $result->successful
                ? 'Mise à jour des paquets système terminée.'
                : 'Échec de la mise à jour système.',
            'output' => trim($result->output !== '' ? $result->output : $result->errorOutput),
        ];
    }

    /**
     * @return array{success: bool, message: string, output: string}
     */
    public function scheduleReboot(int $delaySeconds = 60): array
    {
        if (! $this->canManage()) {
            return [
                'success' => false,
                'message' => 'Redémarrage indisponible sur cette plateforme.',
                'output' => '',
            ];
        }

        $delaySeconds = max(30, min(600, $delaySeconds));
        $result = $this->scripts->run(
            $this->rebootScript(),
            [(string) $delaySeconds, 'Redémarrage planifié par ObiOra Panel'],
            30,
        );

        return [
            'success' => $result->successful,
            'message' => $result->successful
                ? "Redémarrage planifié dans environ {$delaySeconds} secondes."
                : 'Impossible de planifier le redémarrage.',
            'output' => trim($result->output !== '' ? $result->output : $result->errorOutput),
        ];
    }

    /**
     * @return array{manager: ?string, can_update: bool, can_reboot: bool}
     */
    public function detectPackageManager(): array
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return ['manager' => null, 'can_update' => false, 'can_reboot' => false];
        }

        foreach (['apt-get', 'dnf', 'yum'] as $cmd) {
            $check = $this->executor->run('command -v '.escapeshellarg($cmd), ['timeout' => 5]);
            if ($check->successful && trim($check->output) !== '') {
                return [
                    'manager' => $cmd === 'apt-get' ? 'apt' : $cmd,
                    'can_update' => $this->canManage(),
                    'can_reboot' => $this->canManage(),
                ];
            }
        }

        return ['manager' => null, 'can_update' => false, 'can_reboot' => false];
    }

    private function packageUpdateScript(): string
    {
        return $this->agentScript('system-package-update.sh');
    }

    private function rebootScript(): string
    {
        return $this->agentScript('system-reboot.sh');
    }

    private function agentScript(string $filename): string
    {
        return base_path('agent/scripts/'.$filename);
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

/**
 * Publie une archive tar.gz des agents (Doctor, Crash Analyzer) pour install distant curl.
 */
final class AgentBundlePublisher
{
    public static function streamTarGz(string $sourceDir, string $downloadName): StreamedResponse
    {
        abort_unless(is_dir($sourceDir), 404, 'Bundle agent introuvable.');

        return response()->stream(function () use ($sourceDir): void {
            $process = new Process([
                'tar',
                'czf',
                '-',
                '--exclude=__pycache__',
                '--exclude=*.pyc',
                '--exclude=tests',
                '--exclude=.pytest_cache',
                '-C',
                $sourceDir,
                '.',
            ]);
            $process->setTimeout(120);
            $process->run(function (string $type, string $data): void {
                echo $data;
            });

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput() ?: 'Échec création archive agent.'));
            }
        }, 200, [
            'Content-Type' => 'application/gzip',
            'Content-Disposition' => 'attachment; filename="'.$downloadName.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}

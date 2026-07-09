<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\PanelUpdateIntegrity;
use Illuminate\Console\Command;

final class VerifyUpdateIntegrityCommand extends Command
{
    protected $signature = 'obiora:verify-update-integrity';

    protected $description = 'Vérifie que les fichiers critiques du pipeline MAJ sont présents';

    public function handle(PanelUpdateIntegrity $integrity): int
    {
        $result = $integrity->verify(base_path());

        if ($result['missing'] !== []) {
            $this->error('Fichiers MAJ critiques manquants :');
            foreach ($result['missing'] as $path) {
                $this->line("  - {$path}");
            }
        }

        if ($result['warnings'] !== []) {
            $this->warn('Avertissements :');
            foreach ($result['warnings'] as $warning) {
                $this->line("  - {$warning}");
            }
        }

        if ($result['ok']) {
            $this->info('Intégrité MAJ OK.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}

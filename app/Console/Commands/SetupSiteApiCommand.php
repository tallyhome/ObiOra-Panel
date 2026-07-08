<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class SetupSiteApiCommand extends Command
{
    protected $signature = 'obiora:setup-site-api
                            {--ensure : Génère et écrit la clé dans .env si absente}
                            {--quiet-output : Affichage minimal (pour update-panel.sh)}';

    protected $description = 'Configure l\'API démo ObiOra-SiteWeb (OBIORA_SITE_API_KEY)';

    public function handle(): int
    {
        $key = (string) config('obiora.site_api.key');
        $quiet = (bool) $this->option('quiet-output');

        if ($this->option('ensure') && $key === '') {
            $key = Str::random(64);
            $this->writeEnvKey($key);
            $this->callSilent('config:clear');
            $key = (string) config('obiora.site_api.key');
        }

        if ($key === '') {
            if (! $quiet) {
                $this->error('OBIORA_SITE_API_KEY absent.');
                $this->line('Lancez : php artisan obiora:setup-site-api --ensure');
            }

            return self::FAILURE;
        }

        if (! $quiet) {
            $this->info('OBIORA_SITE_API_KEY : OK ('.strlen($key).' caractères)');
            $this->line('TTL démo : '.config('obiora.site_api.demo_ttl_hours', 24).' h');
            $this->line('');
            $this->line('Sur ObiOra-SiteWeb (.env) :');
            $this->line('OBIORA_PANEL_API_KEY='.$key);
            $this->line('');
            $this->line('Vérification : php artisan obiora:site-api-status');
        }

        return self::SUCCESS;
    }

    private function writeEnvKey(string $key): void
    {
        $envPath = base_path('.env');

        if (! is_file($envPath)) {
            $this->error('.env introuvable : '.$envPath);

            return;
        }

        $contents = file_get_contents($envPath);

        if (str_contains($contents, 'OBIORA_SITE_API_KEY=')) {
            $contents = preg_replace(
                '/^OBIORA_SITE_API_KEY=.*$/m',
                'OBIORA_SITE_API_KEY='.$key,
                $contents
            ) ?? $contents;
        } else {
            $contents = rtrim($contents).PHP_EOL.PHP_EOL
                .'# API ObiOra-SiteWeb (démos panel)'.PHP_EOL
                .'OBIORA_SITE_API_KEY='.$key.PHP_EOL
                .'OBIORA_SITE_DEMO_TTL_HOURS=24'.PHP_EOL;
        }

        if (! str_contains($contents, 'OBIORA_SITE_DEMO_TTL_HOURS=')) {
            $contents = rtrim($contents).PHP_EOL.'OBIORA_SITE_DEMO_TTL_HOURS=24'.PHP_EOL;
        }

        file_put_contents($envPath, $contents);

        if (! $this->option('quiet-output')) {
            $this->info('Clé écrite dans .env');
        }
    }
}

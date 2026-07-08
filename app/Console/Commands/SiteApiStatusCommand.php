<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class SiteApiStatusCommand extends Command
{
    protected $signature = 'obiora:site-api-status';

    protected $description = 'Vérifie la configuration API démo pour ObiOra-SiteWeb';

    public function handle(): int
    {
        $key = config('obiora.site_api.key');

        if (! $key) {
            $this->error('OBIORA_SITE_API_KEY absent du .env');
            $this->line('Ajoutez la clé partagée avec ObiOra-SiteWeb, puis : php artisan config:clear');

            return self::FAILURE;
        }

        $this->info('OBIORA_SITE_API_KEY : configurée ('.strlen($key).' caractères)');
        $this->info('TTL démo : '.config('obiora.site_api.demo_ttl_hours', 24).' h');
        $this->line('');
        $this->line('Endpoints actifs :');
        $this->line('  GET  /api/v1/site-api/ping');
        $this->line('  POST /api/v1/demo-accounts');
        $this->line('  DELETE /api/v1/demo-accounts/{id}');
        $this->line('');
        $this->warn('La commande obiora:check-panel se lance sur ObiOra-SiteWeb, pas ici.');

        return self::SUCCESS;
    }
}

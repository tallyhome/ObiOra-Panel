<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\Core\ServerManager;
use App\Services\System\ServiceManager;

final class AiActionExecutor
{
    public function __construct(
        private readonly ServiceManager $services,
        private readonly ServerManager $servers,
    ) {}

    /**
     * @return array{handled: bool, message: string}
     */
    public function tryExecute(string $userMessage): array
    {
        if (! preg_match('/red[ée]marre(?:r)?\s+(nginx|mysql|mariadb|redis|webmin|php-fpm|docker)/iu', $userMessage, $matches)) {
            return ['handled' => false, 'message' => ''];
        }

        $service = match (strtolower($matches[1])) {
            'nginx' => 'nginx',
            'mysql', 'mariadb' => 'mariadb',
            'redis' => 'redis',
            'webmin' => 'webmin',
            'php-fpm' => 'php-fpm',
            'docker' => 'docker',
            default => null,
        };

        if ($service === null) {
            return ['handled' => false, 'message' => ''];
        }

        $server = $this->servers->getCurrentServer();
        if ($server === null) {
            return ['handled' => true, 'message' => 'Aucun serveur sélectionné pour redémarrer '.$service.'.'];
        }

        $result = $this->services->action($service, 'restart');

        return [
            'handled' => true,
            'message' => $result['success']
                ? "Action panel : service « {$service} » redémarré."
                : 'Échec redémarrage « '.$service.' » : '.trim($result['output']),
        ];
    }
}

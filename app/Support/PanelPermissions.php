<?php

declare(strict_types=1);

namespace App\Support;

final class PanelPermissions
{
    /** @var list<string> */
    public const ALL = [
        'dashboard.view',
        'servers.view',
        'servers.manage',
        'services.view',
        'services.manage',
        'websites.view',
        'websites.manage',
        'databases.view',
        'databases.manage',
        'docker.view',
        'docker.manage',
        'backups.view',
        'backups.manage',
        'users.view',
        'users.manage',
        'modules.view',
        'modules.manage',
        'plugins.view',
        'plugins.manage',
        'updates.view',
        'updates.manage',
        'license.view',
        'license.manage',
        'ai.view',
        'ai.manage',
        'monitoring.view',
    ];

    /** @return list<string> */
    public static function forRole(string $role): array
    {
        return match ($role) {
            'super-admin' => self::ALL,
            'admin' => [
                'dashboard.view',
                'servers.view',
                'servers.manage',
                'services.view',
                'services.manage',
                'websites.view',
                'websites.manage',
                'databases.view',
                'databases.manage',
                'docker.view',
                'docker.manage',
                'backups.view',
                'backups.manage',
                'plugins.view',
                'plugins.manage',
                'modules.view',
                'modules.manage',
                'ai.view',
                'ai.manage',
                'monitoring.view',
                'updates.view',
            ],
            'technician' => [
                'dashboard.view',
                'servers.view',
                'services.view',
                'services.manage',
                'databases.view',
                'docker.view',
                'docker.manage',
                'backups.view',
                'plugins.view',
                'modules.view',
                'monitoring.view',
                'ai.view',
            ],
            'client' => [
                'dashboard.view',
                'services.view',
                'plugins.view',
                'plugins.manage',
                'websites.view',
                'websites.manage',
                'databases.view',
                'ai.view',
            ],
            default => [],
        };
    }

    /** @return list<string> */
    public static function roleNames(): array
    {
        return ['super-admin', 'admin', 'technician', 'client'];
    }
}

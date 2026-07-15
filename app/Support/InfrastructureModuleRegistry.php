<?php

declare(strict_types=1);

namespace App\Support;

final class InfrastructureModuleRegistry
{
    /**
     * Modules Infrastructure avec pages métier (Phase 13).
     *
     * @return array<string, array{route: string, name: string, icon: string}>
     */
    public static function implemented(): array
    {
        return [
            'ssl' => ['route' => 'ssl.index', 'name' => 'SSL / TLS', 'icon' => '🔒'],
            'firewall' => ['route' => 'firewall.index', 'name' => 'Firewall', 'icon' => '🛡'],
            'users' => ['route' => 'users.index', 'name' => 'Utilisateurs', 'icon' => '👤'],
            'nginx' => ['route' => 'nginx.index', 'name' => 'Nginx', 'icon' => '◎'],
            'redis' => ['route' => 'redis.index', 'name' => 'Redis', 'icon' => '⚡'],
            'apache' => ['route' => 'apache.index', 'name' => 'Apache', 'icon' => '🅰'],
            'ftp' => ['route' => 'ftp.index', 'name' => 'FTP', 'icon' => '📁'],
            'dns' => ['route' => 'dns.index', 'name' => 'DNS', 'icon' => '🌐'],
            'applications' => ['route' => 'applications.index', 'name' => 'Applications', 'icon' => '📦'],
            'virtualizor' => ['route' => 'virtualizor.index', 'name' => 'Virtualizor', 'icon' => '☁'],
            'cluster' => ['route' => 'cluster.index', 'name' => 'Cluster', 'icon' => '⬡'],
        ];
    }

    /** Lien principal sidebar (hors section Infrastructure). */
    public static function sidebarPrimary(): array
    {
        return self::diagnostics()['doctor'];
    }

    /**
     * Diagnostics : Doctor & Suite + Crash Analyzer (sidebar).
     *
     * @return array<string, array{route: string, name: string, icon: string, active: list<string>}>
     */
    public static function diagnostics(): array
    {
        return [
            'doctor' => [
                'route' => 'doctor.index',
                'name' => 'Doctor & Suite',
                'icon' => '🩺',
                'active' => ['doctor.*'],
            ],
            'crash-analyzer' => [
                'route' => 'crash-analyzer.index',
                'name' => 'Crash Analyzer',
                'icon' => '💥',
                'active' => ['crash-analyzer.*'],
            ],
            'security' => [
                'route' => 'security.index',
                'name' => 'Sécurité',
                'icon' => '🔐',
                'active' => ['security.*'],
            ],
        ];
    }

    public static function isDiagnosticRouteActive(string $slug): bool
    {
        $item = self::diagnostics()[$slug] ?? null;

        if ($item === null) {
            return false;
        }

        foreach ($item['active'] as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function isImplemented(string $slug): bool
    {
        return isset(self::implemented()[$slug]);
    }

    /**
     * @return list<string>
     */
    public static function implementedSlugs(): array
    {
        return array_keys(self::implemented());
    }
}

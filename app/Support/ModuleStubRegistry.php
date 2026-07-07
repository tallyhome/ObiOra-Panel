<?php

declare(strict_types=1);

namespace App\Support;

final class ModuleStubRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'firewall' => self::entry(
                'Firewall',
                'UFW, Fail2Ban et politiques de pare-feu centralisées.',
                '🛡',
                'Phase 11+ — regles UFW/fail2ban depuis le panel.',
                [['route' => 'services.index', 'label' => 'Services systemd']],
            ),
            'ftp' => self::entry(
                'FTP',
                'Pure-FTPd / vsftpd — comptes FTP et quotas.',
                '📁',
                'Gestion comptes FTP liee au marketplace (pure-ftpd, vsftpd).',
                [['route' => 'plugins.index', 'label' => 'Marketplace FTP']],
            ),
            'dns' => self::entry(
                'DNS',
                'Zones DNS locales et enregistrements pour vos domaines.',
                '🌐',
                'Integration Bind/Unbound ou API registrar.',
                [['route' => 'websites.index', 'label' => 'Sites web']],
            ),
            'cluster' => self::entry(
                'Cluster',
                'Orchestration multi-nœuds et basculement.',
                '⬡',
                'Haute disponibilite panel + agents (Phase future).',
                [['route' => 'servers.index', 'label' => 'Serveurs']],
            ),
            'virtualizor' => self::entry(
                'Virtualizor',
                'Integration API Virtualizor pour VPS clients.',
                '☁',
                'Provisioning VPS via API Virtualizor.',
                [['route' => 'servers.create', 'label' => 'Ajouter serveur']],
            ),
            'users' => self::entry(
                'Utilisateurs',
                'Comptes panel, roles RBAC et permissions Spatie.',
                '👤',
                'CRUD utilisateurs, invitation, quotas par plan licence.',
                [],
            ),
            'apache' => self::entry(
                'Apache',
                'Virtual hosts Apache httpd (alternative Nginx).',
                '🅰',
                'Sites web Apache — complement du module Nginx.',
                [['route' => 'websites.index', 'label' => 'Sites web (Nginx)']],
            ),
            'redis' => self::entry(
                'Redis',
                'Cache Redis, sessions et files d\'attente.',
                '⚡',
                'Monitoring Redis, flush cache, persistence RDB/AOF.',
                [['route' => 'services.index', 'label' => 'Service redis']],
            ),
            'nginx' => self::entry(
                'Nginx',
                'Configuration Nginx avancee (vhosts, upstreams, cache).',
                '◎',
                'Edition vhosts au-dela du provisioning sites web.',
                [['route' => 'websites.index', 'label' => 'Sites web']],
            ),
            'ssl' => self::entry(
                'SSL / TLS',
                'Certificats Let\'s Encrypt, renouvellement et alertes.',
                '🔒',
                'Vue centralisee certificats (au-dela du certbot par site).',
                [['route' => 'monitoring.index', 'label' => 'Monitoring Doctor']],
            ),
            'applications' => self::entry(
                'Applications',
                'Inventaire apps installees hors marketplace.',
                '📦',
                'Vue unifiee des packages systeme et conteneurs.',
                [['route' => 'plugins.index', 'label' => 'Marketplace']],
            ),
            'ai' => self::entry(
                'Assistant IA',
                'Diagnostic assiste et suggestions (Phase 12).',
                '🤖',
                'Chat contextuel panel + rapports Doctor — voir PHASE-12.md.',
                [['route' => 'monitoring.index', 'label' => 'Monitoring']],
            ),
        ];
    }

    /**
     * Modules infrastructure (sans assistant IA).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function infrastructure(): array
    {
        $all = self::all();
        unset($all['ai']);

        foreach (InfrastructureModuleRegistry::implementedSlugs() as $slug) {
            unset($all[$slug]);
        }

        return $all;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $slug): ?array
    {
        $entry = self::all()[$slug] ?? null;
        if ($entry === null) {
            return null;
        }

        return array_merge($entry, ['slug' => $slug]);
    }

    /**
     * @param  list<array{route: string, label: string}>  $links
     * @return array<string, mixed>
     */
    private static function entry(string $name, string $description, string $icon, string $planned, array $links): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'icon' => $icon,
            'planned' => $planned,
            'links' => $links,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DedicatedHostProfile;
use App\Models\Server;

/**
 * Registre des profils « hôte dédié » — générique au-delà de Virtualizor.
 *
 * Chaque profil décrit les modules Doctor pertinents, les politiques d'alerte
 * recommandées et les liens panel associés.
 */
final class DedicatedHostProfileRegistry
{
    /**
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     doctor_modules: list<string>,
     *     alert_hints: list<string>,
     *     panel_routes: list<array{label: string, route: string|null}>,
     *     install_detect_paths: list<string>,
     *     default_ssh_port: int,
     *     kvm_udev: bool,
     * }>
     */
    public static function definitions(): array
    {
        return [
            DedicatedHostProfile::BareMetal->value => [
                'label' => 'Dédié bare metal',
                'description' => 'Serveur dédié classique (OVH, Hetzner, SoYouStart…) sans hyperviseur panel ObiOra.',
                'doctor_modules' => ['cpu', 'ram', 'disk', 'raid', 'smart', 'network', 'nginx', 'mysql', 'docker', 'security', 'obiora', 'malware', 'firewall'],
                'alert_hints' => ['cpu_usage_percent', 'memory_usage_percent', 'disk_usage_percent', 'load_per_core'],
                'panel_routes' => [],
                'install_detect_paths' => [],
                'default_ssh_port' => 22,
                'kvm_udev' => false,
            ],
            DedicatedHostProfile::Virtualizor->value => [
                'label' => 'Virtualizor (KVM)',
                'description' => 'Nœud Virtualizor / libvirt — correctifs KVM, port SSH dédié, module Doctor Virtualizor.',
                'doctor_modules' => ['virtualizor', 'cpu', 'ram', 'disk', 'kvm', 'network', 'docker', 'mysql', 'security', 'obiora', 'malware', 'firewall'],
                'alert_hints' => ['cpu_steal_percent', 'cpu_usage_percent', 'disk_usage_percent', 'memory_usage_percent'],
                'panel_routes' => [
                    ['label' => 'Module Virtualizor', 'route' => 'virtualizor.index'],
                ],
                'install_detect_paths' => ['/usr/local/virtualizor', '/usr/local/virtualizor/version'],
                'default_ssh_port' => 2212,
                'kvm_udev' => true,
            ],
            DedicatedHostProfile::Proxmox->value => [
                'label' => 'Proxmox VE',
                'description' => 'Hyperviseur Proxmox — surveillance CPU steal, stockage ZFS/LVM, cluster PVE.',
                'doctor_modules' => ['cpu', 'ram', 'disk', 'network', 'docker', 'security', 'obiora', 'malware', 'firewall'],
                'alert_hints' => ['cpu_steal_percent', 'cpu_usage_percent', 'disk_usage_percent', 'memory_usage_percent'],
                'panel_routes' => [],
                'install_detect_paths' => ['/etc/pve', '/usr/sbin/pveversion'],
                'default_ssh_port' => 22,
                'kvm_udev' => true,
            ],
            DedicatedHostProfile::SolusVm->value => [
                'label' => 'SolusVM',
                'description' => 'Nœud SolusVM / SolusVM 2 — KVM ou OpenVZ selon installation.',
                'doctor_modules' => ['cpu', 'ram', 'disk', 'network', 'security', 'obiora', 'malware', 'firewall'],
                'alert_hints' => ['cpu_steal_percent', 'cpu_usage_percent', 'disk_usage_percent'],
                'panel_routes' => [],
                'install_detect_paths' => ['/usr/local/solusvm', '/etc/solusvm'],
                'default_ssh_port' => 22,
                'kvm_udev' => true,
            ],
            DedicatedHostProfile::Custom->value => [
                'label' => 'Autre / personnalisé',
                'description' => 'Profil générique — hooks install manuels, modules Doctor standard.',
                'doctor_modules' => ['cpu', 'ram', 'disk', 'network', 'security', 'obiora', 'malware', 'firewall'],
                'alert_hints' => ['cpu_usage_percent', 'memory_usage_percent', 'disk_usage_percent'],
                'panel_routes' => [],
                'install_detect_paths' => [],
                'default_ssh_port' => 22,
                'kvm_udev' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function definition(DedicatedHostProfile $profile): ?array
    {
        if ($profile === DedicatedHostProfile::Auto) {
            return null;
        }

        return self::definitions()[$profile->value] ?? null;
    }

    public static function resolve(Server $server): DedicatedHostProfile
    {
        $raw = ($server->metadata ?? [])['host_profile'] ?? null;

        if (is_string($raw) && $raw !== '') {
            $profile = DedicatedHostProfile::tryFrom($raw);

            if ($profile !== null && $profile !== DedicatedHostProfile::Auto) {
                return $profile;
            }
        }

        if ($server->type === \App\Enums\ServerType::Dedicated) {
            return DedicatedHostProfile::BareMetal;
        }

        return DedicatedHostProfile::BareMetal;
    }

    /** @return list<array{label: string, route: string}> */
    public static function panelLinks(Server $server): array
    {
        $profile = self::resolve($server);
        $def = self::definition($profile);

        if ($def === null) {
            return [];
        }

        $links = [];

        foreach ($def['panel_routes'] as $entry) {
            if ($entry['route'] === null) {
                continue;
            }

            try {
                $links[] = [
                    'label' => $entry['label'],
                    'route' => route($entry['route']),
                ];
            } catch (\Throwable) {
                // Module optionnel non activé
            }
        }

        return $links;
    }

    public static function labelFor(Server $server): string
    {
        return self::definition(self::resolve($server))['label']
            ?? self::resolve($server)->label();
    }
}

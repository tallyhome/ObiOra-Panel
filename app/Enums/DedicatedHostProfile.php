<?php

declare(strict_types=1);

namespace App\Enums;

enum DedicatedHostProfile: string
{
    case Auto = 'auto';
    case BareMetal = 'bare_metal';
    case Virtualizor = 'virtualizor';
    case Proxmox = 'proxmox';
    case SolusVm = 'solusvm';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Auto (détection)',
            self::BareMetal => 'Dédié bare metal',
            self::Virtualizor => 'Virtualizor (KVM)',
            self::Proxmox => 'Proxmox VE',
            self::SolusVm => 'SolusVM',
            self::Custom => 'Autre / personnalisé',
        };
    }

    /** Profils proposés à l'enregistrement d'un serveur (sans Auto). */
    public static function selectable(): array
    {
        return [
            self::BareMetal,
            self::Virtualizor,
            self::Proxmox,
            self::SolusVm,
            self::Custom,
        ];
    }
}

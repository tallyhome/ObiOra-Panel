<?php

declare(strict_types=1);

namespace App\Support;

final class CrashAnalyzerTriggerLabels
{
    /** @var array<string, string> */
    private const LABELS = [
        'kernel_panic' => 'Kernel panic — le noyau Linux a planté',
        'hard_lockup' => 'Hard lockup — CPU bloqué en kernel',
        'soft_lockup' => 'Soft lockup — scheduler bloqué',
        'rcu_stall' => 'RCU stall — blocage RCU (souvent I/O ou storage)',
        'oom_killer' => 'OOM Killer — mémoire saturée, processus tué',
        'unexpected_reboot' => 'Reboot inattendu sans arrêt propre',
        'ecc_error' => 'Erreur ECC / Machine Check — matériel mémoire ou CPU',
        'memory_pressure' => 'Pression mémoire élevée',
        'virtualizor_crash' => 'Erreur Virtualizor / libvirt',
        'nvme_error' => 'Erreur NVMe / disque',
        'filesystem_ro' => 'Filesystem passé en lecture seule',
    ];

    /** @var array<string, list<string>> */
    private const HINTS = [
        'rcu_stall' => [
            'Vérifier latence disque et logs NVMe/RAID.',
            'Corréler avec PSI I/O et processus en D-state.',
        ],
        'ecc_error' => [
            'Erreur matérielle probable — consulter IPMI/SEL et logs EDAC.',
            'Planifier test mémoire (memtest) ou intervention datacenter.',
        ],
        'oom_killer' => [
            'Identifier le processus tué dans journalctl -k.',
            'Ajuster limites mémoire ou augmenter RAM/swap.',
        ],
    ];

    public static function label(?string $trigger): string
    {
        if ($trigger === null || $trigger === '') {
            return 'Événement inconnu';
        }

        return self::LABELS[$trigger] ?? str_replace('_', ' ', ucfirst($trigger));
    }

    /**
     * @return list<string>
     */
    public static function hints(?string $trigger): array
    {
        return self::HINTS[$trigger] ?? [
            'Consulter les événements et métriques de la même période.',
            'Comparer avec un rapport CrashHunter Black Box si disponible.',
        ];
    }
}

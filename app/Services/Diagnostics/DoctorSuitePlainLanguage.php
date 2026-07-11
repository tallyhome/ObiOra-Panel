<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Support\DiagnosticConfidence;

/**
 * Transforme les données brutes Doctor / Crash Analyzer / CrashHunter
 * en synthèse lisible pour la page Doctor & Suite.
 */
final class DoctorSuitePlainLanguage
{
    /** @var array<string, list<string>> */
    private const CRASH_ANALYZER_HINTS = [
        'kernel_panic' => [
            'Le noyau Linux a planté — consultez dmesg et journalctl -b -1.',
            'Activez kdump pour capturer une vmcore au prochain crash.',
        ],
        'hard_lockup' => [
            'Un cœur CPU est bloqué depuis plusieurs secondes (bug driver ou kernel).',
            'Vérifiez les modules récemment chargés et les mises à jour kernel en attente.',
        ],
        'soft_lockup' => [
            'Le scheduler n\'a pas tourné sur un CPU — freeze partiel probable.',
            'Corréler avec la charge VM et les processus en D-state.',
        ],
        'rcu_stall' => [
            'Le mécanisme RCU du kernel est bloqué — souvent I/O disque ou driver storage.',
            'Inspectez les logs NVMe/RAID et la latence disque.',
        ],
        'oom_killer' => [
            'La RAM est saturée — un processus a été tué par le kernel.',
            'Identifiez le coupable (journalctl -k | grep -i oom) et ajustez limites mémoire.',
        ],
        'unexpected_reboot' => [
            'Le serveur a redémarré sans arrêt propre.',
            'Comparez avec le rapport CrashHunter Black Box pour la chronologie pré-reboot.',
        ],
        'memory_pressure' => [
            'Pression mémoire élevée — risque de freeze ou OOM imminent.',
            'Surveillez swap, PSI memory et les VM les plus gourmandes.',
        ],
        'virtualizor_crash' => [
            'Virtualizor ou libvirt a signalé une erreur critique.',
            'Vérifiez les logs Virtualizor, l\'espace disque VM et l\'état libvirt.',
        ],
    ];

    /** @var array<string, string> */
    private const INCIDENT_TRIGGER_LABELS = [
        'iowait_spike' => 'Pic d\'I/O wait — disque saturé ou bloqué',
        'd_state_processes' => 'Processus bloqués en D-state (I/O kernel)',
        'scheduler_stall' => 'Scheduler bloqué — freeze système probable',
        'ssh_timeout' => 'SSH ne répond plus — freeze silencieux',
        'command_timeout' => 'Commandes système en timeout',
        'qemu_stall' => 'VM QEMU/KVM figée',
        'virtualizor_timeout' => 'Virtualizor ne répond plus',
        'load_spike' => 'Charge CPU/load anormale',
        'memory_pressure' => 'Pression mémoire critique',
    ];

    /**
     * @param  array<string, mixed>  $overview
     * @return array<string, mixed>
     */
    public function summarize(array $overview): array
    {
        $items = [];

        $hunter = $overview['crash_hunter']['latest_report_insights'] ?? null;
        $confidence = null;
        if (is_array($hunter) && ($hunter['verdict'] ?? null) !== null) {
            $confidence = $hunter['confidence_display'] ?? DiagnosticConfidence::format($hunter['confidence'] ?? null);
            $items[] = [
                'source' => 'CrashHunter',
                'kind' => 'crash',
                'severity' => 'critical',
                'title' => (string) $hunter['verdict'],
                'subtitle' => $this->joinParts([
                    $hunter['reboot_classification'] ?? null,
                    $hunter['reboot_reason'] ?? null,
                ]),
                'explanation' => (string) ($hunter['diagnosis_summary'] ?? $hunter['causal_story'] ?? ''),
                'actions' => $this->normalizeRecommendations($hunter['recommendations'] ?? []),
                'confidence' => $confidence,
            ];
        }

        foreach ($overview['crash_hunter']['incidents'] ?? [] as $incident) {
            if (! is_array($incident)) {
                continue;
            }
            $triggers = $incident['triggers'] ?? [];
            if ($triggers === []) {
                continue;
            }
            $labels = array_map(
                fn (string $t) => self::INCIDENT_TRIGGER_LABELS[$t] ?? str_replace('_', ' ', $t),
                array_map('strval', $triggers),
            );
            $items[] = [
                'source' => 'CrashHunter',
                'kind' => 'freeze',
                'severity' => ($incident['status'] ?? '') === 'active' ? 'critical' : 'warning',
                'title' => 'Incident freeze détecté',
                'subtitle' => implode(' · ', array_slice($labels, 0, 3)),
                'explanation' => sprintf(
                    '%d snapshot(s) d\'urgence capturé(s) — analyse Black Box disponible.',
                    (int) ($incident['snapshot_count'] ?? 0),
                ),
                'actions' => [
                    'Consultez le rapport Black Box pour la chronologie complète.',
                    'Vérifiez iowait, D-state et latence disque sur la période de l\'incident.',
                ],
            ];
        }

        foreach ($overview['crash_analyzer']['events'] ?? [] as $event) {
            if (! is_array($event) || ($event['severity'] ?? '') !== 'critical') {
                continue;
            }
            $type = (string) ($event['event_type'] ?? '');
            $items[] = [
                'source' => 'Crash Analyzer',
                'kind' => 'event',
                'severity' => 'critical',
                'title' => (string) ($event['title'] ?? $type),
                'subtitle' => $type !== '' ? $type : null,
                'explanation' => (string) ($event['details'] ?? ''),
                'actions' => self::CRASH_ANALYZER_HINTS[$type] ?? [
                    'Consultez les détails de l\'événement dans Crash Analyzer.',
                    'Corréler avec les métriques CPU/RAM/IO de la même période.',
                ],
            ];
        }

        foreach ($overview['doctor']['action_findings'] ?? [] as $finding) {
            if (! is_array($finding)) {
                continue;
            }
            $level = (string) ($finding['level'] ?? '');
            $items[] = [
                'source' => 'Doctor',
                'kind' => 'audit',
                'severity' => $level === 'CRITICAL' ? 'critical' : 'warning',
                'title' => (string) ($finding['title'] ?? ''),
                'subtitle' => (string) ($finding['module'] ?? ''),
                'explanation' => (string) ($finding['details'] ?? ''),
                'actions' => array_values(array_filter([
                    $finding['recommendation'] ?? null,
                ])),
            ];
        }

        $items = $this->dedupeItems($items);
        $severity = $this->resolveOverallSeverity($items);

        return [
            'severity' => $severity,
            'headline' => $this->buildHeadline($items, $severity),
            'subtitle' => $this->buildSubtitle($overview, $items),
            'confidence' => $confidence,
            'items' => array_slice($items, 0, 8),
            'agents_status' => [
                'doctor' => ($overview['doctor']['score'] ?? null) !== null,
                'crash_analyzer' => (($overview['crash_analyzer']['summary']['metrics_count'] ?? 0) > 0),
                'crash_hunter' => ! empty($overview['crash_hunter']['summary']),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $recs
     * @return list<string>
     */
    public function normalizeRecommendations(array $recs): array
    {
        $actions = [];

        foreach ($recs as $rec) {
            if (! is_array($rec)) {
                if (is_string($rec) && $rec !== '') {
                    $actions[] = $rec;
                }

                continue;
            }

            if (! empty($rec['actions']) && is_array($rec['actions'])) {
                foreach ($rec['actions'] as $action) {
                    if (is_string($action) && $action !== '') {
                        $actions[] = $action;
                    }
                }

                continue;
            }

            $line = trim(implode(' — ', array_filter([
                $rec['priority'] ?? null,
                $rec['action'] ?? null,
                $rec['title'] ?? null,
                $rec['detail'] ?? null,
            ])));

            if ($line !== '') {
                $actions[] = $line;
            }
        }

        return array_values(array_unique($actions));
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    public function extractActionFindings(array $json): array
    {
        $findings = [];

        foreach ($json['results'] ?? [] as $result) {
            if (! is_array($result)) {
                continue;
            }
            foreach ($result['findings'] ?? [] as $finding) {
                if (! is_array($finding)) {
                    continue;
                }
                $level = (string) ($finding['level'] ?? '');
                if (! in_array($level, ['CRITICAL', 'WARNING'], true)) {
                    continue;
                }
                $findings[] = [
                    'module' => (string) ($result['module'] ?? 'unknown'),
                    'level' => $level,
                    'title' => (string) ($finding['title'] ?? ''),
                    'details' => (string) ($finding['details'] ?? ''),
                    'recommendation' => isset($finding['recommendation'])
                        ? (string) $finding['recommendation']
                        : null,
                ];
            }
        }

        usort($findings, static function (array $a, array $b): int {
            $order = ['CRITICAL' => 0, 'WARNING' => 1];

            return ($order[$a['level']] ?? 2) <=> ($order[$b['level']] ?? 2);
        });

        return array_slice($findings, 0, 12);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function dedupeItems(array $items): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $key = mb_strtolower(($item['source'] ?? '').':'.($item['title'] ?? ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function resolveOverallSeverity(array $items): string
    {
        foreach ($items as $item) {
            if (($item['severity'] ?? '') === 'critical') {
                return 'critical';
            }
        }

        return $items === [] ? 'ok' : 'warning';
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function buildHeadline(array $items, string $severity): string
    {
        if ($severity === 'ok') {
            return 'Aucun crash ni freeze détecté récemment';
        }

        $primary = $items[0] ?? null;
        if ($primary === null) {
            return 'Anomalies détectées — voir le détail ci-dessous';
        }

        return (string) ($primary['title'] ?? 'Anomalies détectées');
    }

    /**
     * @param  array<string, mixed>  $overview
     * @param  list<array<string, mixed>>  $items
     */
    private function buildSubtitle(array $overview, array $items): ?string
    {
        if ($items !== [] && ! empty($items[0]['subtitle'])) {
            return (string) $items[0]['subtitle'];
        }

        $hunter = $overview['crash_hunter']['summary'] ?? [];
        if (($hunter['incident_mode'] ?? false) === true) {
            return 'Mode incident actif — collecte d\'urgence en cours';
        }

        if (($hunter['witness_status'] ?? '') === 'stale') {
            return 'CrashHunter n\'a pas envoyé de signe de vie récemment';
        }

        return null;
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function joinParts(array $parts): ?string
    {
        $filtered = array_values(array_filter($parts, static fn (?string $p) => $p !== null && $p !== ''));

        return $filtered === [] ? null : implode(' · ', $filtered);
    }
}

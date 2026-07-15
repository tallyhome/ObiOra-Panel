<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\DiagnosticReport;
use App\Models\Server;
use App\Services\Diagnostics\DoctorSuitePlainLanguage;
use Illuminate\Support\Collection;

/**
 * Agrège les findings sécurité depuis les rapports Doctor.
 */
final class SecurityAuditService
{
    /** @var list<string> */
    private const SECURITY_MODULES = [
        'security',
        'obiora',
        'firewall',
        'malware',
        'network',
        'ssl',
        'accounts',
        'persistence',
        'privesc',
        'auth_logs',
        'web_perms',
        'docker_security',
        'lynis',
        'mail_dns',
        'waf',
        'hosting_security',
    ];

    /** @var array<string, int> */
    private const PRIORITY_MAP = [
        'CRITICAL' => 0,
        'WARNING' => 1,
        'INFO' => 2,
    ];

    public function __construct(
        private readonly DoctorSuitePlainLanguage $plainLanguage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function serverAudit(Server $server): array
    {
        $report = $server->latestDiagnosticReport;
        $meta = $server->metadata ?? [];
        $eligible = $this->isEligible($server);

        if ($report === null) {
            return [
                'eligible' => $eligible,
                'has_report' => false,
                'score' => null,
                'security_score' => null,
                'status' => 'unknown',
                'generated_at' => null,
                'findings' => [],
                'plan' => [],
                'modules' => [],
                'doctor_installed' => (bool) ($meta['doctor'] ?? $meta['doctor_deploy'] ?? false),
                'message' => $eligible
                    ? 'Aucun rapport Doctor — lancez un scan ou installez l\'agent Doctor.'
                    : 'Ce serveur n\'est pas éligible à l\'audit sécurité Obiora.',
            ];
        }

        $json = $report->report_json ?? [];
        $securityFindings = $this->extractSecurityFindings($json);
        $securityScore = $this->computeSecurityScore($securityFindings);
        $plan = $this->buildPlan($securityFindings);

        return [
            'eligible' => $eligible,
            'has_report' => true,
            'score' => $report->score,
            'security_score' => $securityScore,
            'status' => $this->resolveStatus($securityScore, $securityFindings),
            'generated_at' => $report->generated_at?->toIso8601String(),
            'findings' => $securityFindings,
            'plan' => $plan,
            'modules' => $this->extractSecurityModules($json),
            'doctor_installed' => true,
            'doctor_version' => $report->doctor_version,
            'signature_verified' => $report->signature_verified,
            'critical_count' => count(array_filter($securityFindings, fn (array $f) => ($f['level'] ?? '') === 'CRITICAL')),
            'warning_count' => count(array_filter($securityFindings, fn (array $f) => ($f['level'] ?? '') === 'WARNING')),
        ];
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return list<array<string, mixed>>
     */
    public function fleetOverview(Collection $servers): array
    {
        return $servers
            ->filter(fn (Server $s) => $this->isEligible($s))
            ->map(function (Server $server) {
                $audit = $this->serverAudit($server);

                return [
                    'id' => $server->id,
                    'name' => $server->name,
                    'is_master' => (bool) $server->is_master,
                    'security_score' => $audit['security_score'],
                    'status' => $audit['status'],
                    'critical_count' => $audit['critical_count'] ?? 0,
                    'warning_count' => $audit['warning_count'] ?? 0,
                    'generated_at' => $audit['generated_at'],
                    'has_report' => $audit['has_report'],
                ];
            })
            ->values()
            ->all();
    }

    public function isEligible(Server $server): bool
    {
        if ($server->is_master || $server->type->value === 'local') {
            return true;
        }

        $meta = $server->metadata ?? [];

        return isset($meta['slave_deploy'])
            || isset($meta['doctor_deploy'])
            || isset($meta['doctor'])
            || $server->diagnosticReports()->exists();
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    public function extractSecurityFindings(array $json): array
    {
        $findings = [];

        foreach ($json['results'] ?? [] as $result) {
            $module = (string) ($result['module'] ?? '');
            if (! in_array($module, self::SECURITY_MODULES, true)) {
                continue;
            }

            foreach ($result['findings'] ?? [] as $finding) {
                if (! is_array($finding)) {
                    continue;
                }
                $level = (string) ($finding['level'] ?? 'INFO');
                if ($level === 'INFO') {
                    continue;
                }
                $findings[] = [
                    'module' => $module,
                    'level' => $level,
                    'title' => (string) ($finding['title'] ?? ''),
                    'details' => (string) ($finding['details'] ?? ''),
                    'recommendation' => (string) ($finding['recommendation'] ?? ''),
                    'commands' => is_array($finding['commands'] ?? null) ? $finding['commands'] : [],
                    'priority' => self::PRIORITY_MAP[$level] ?? 2,
                    'harden_action' => $this->mapHardenAction($module, (string) ($finding['title'] ?? '')),
                ];
            }
        }

        usort($findings, fn (array $a, array $b) => ($a['priority'] ?? 9) <=> ($b['priority'] ?? 9));

        return $findings;
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return list<array<string, mixed>>
     */
    public function buildPlan(array $findings): array
    {
        $groups = ['P0' => [], 'P1' => [], 'P2' => [], 'P3' => []];

        foreach ($findings as $finding) {
            $level = (string) ($finding['level'] ?? '');
            $key = match ($level) {
                'CRITICAL' => 'P0',
                'WARNING' => 'P1',
                default => 'P2',
            };
            $groups[$key][] = [
                'title' => $finding['title'],
                'module' => $finding['module'],
                'details' => $finding['details'],
                'recommendation' => $finding['recommendation'],
                'harden_action' => $finding['harden_action'] ?? null,
                'commands' => $finding['commands'] ?? [],
            ];
        }

        $plan = [];
        foreach ($groups as $priority => $items) {
            if ($items === []) {
                continue;
            }
            $plan[] = [
                'priority' => $priority,
                'label' => match ($priority) {
                    'P0' => 'Critique — traiter immédiatement',
                    'P1' => 'Important — à planifier',
                    'P2' => 'Recommandé',
                    'P3' => 'Bonnes pratiques',
                },
                'items' => $items,
            ];
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    private function extractSecurityModules(array $json): array
    {
        $modules = [];
        foreach ($json['results'] ?? [] as $result) {
            $name = (string) ($result['module'] ?? '');
            if (! in_array($name, self::SECURITY_MODULES, true)) {
                continue;
            }
            $modules[] = [
                'module' => $name,
                'status' => $result['status'] ?? 'ok',
                'score' => $result['score'] ?? null,
                'findings_count' => count($result['findings'] ?? []),
            ];
        }

        return $modules;
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     */
    private function computeSecurityScore(array $findings): int
    {
        $score = 100;
        foreach ($findings as $finding) {
            $score -= match ($finding['level'] ?? '') {
                'CRITICAL' => 25,
                'WARNING' => 10,
                default => 0,
            };
        }

        return max(0, min(100, $score));
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     */
    private function resolveStatus(int $score, array $findings): string
    {
        foreach ($findings as $finding) {
            if (($finding['level'] ?? '') === 'CRITICAL') {
                return 'critical';
            }
        }
        if ($score < 70) {
            return 'warning';
        }

        return 'ok';
    }

    private function mapHardenAction(string $module, string $title): ?string
    {
        $titleLower = mb_strtolower($title);

        return match (true) {
            str_contains($titleLower, 'fail2ban') => 'enable-fail2ban',
            str_contains($titleLower, 'pare-feu') || str_contains($titleLower, 'firewall') || str_contains($titleLower, 'ufw') => 'enable-firewall',
            str_contains($titleLower, 'permissions') || str_contains($titleLower, '.env') => 'secure-env-perms',
            str_contains($titleLower, 'scanner rootkit') || str_contains($titleLower, 'rkhunter') || str_contains($titleLower, 'rootkit') => 'install-rkhunter',
            default => null,
        };
    }
}

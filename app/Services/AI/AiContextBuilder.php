<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\License;
use App\Models\MonitoringAlert;
use App\Services\Core\ServerManager;

final class AiContextBuilder
{
    public function __construct(
        private readonly ServerManager $servers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $server = $this->servers->getCurrentServer();
        $report = $server?->latestDiagnosticReport;

        $context = [
            'panel_version' => config('obiora.version'),
            'server' => $server ? [
                'id' => $server->id,
                'name' => $server->name,
                'ip' => $server->ip_address,
                'status' => $server->status->value ?? (string) $server->status,
            ] : null,
            'doctor' => $report ? [
                'score' => $report->score,
                'status' => $report->status,
                'critical_count' => count($report->critical_findings ?? []),
                'generated_at' => $report->generated_at?->toIso8601String(),
            ] : null,
            'alerts_unread' => MonitoringAlert::query()->whereNull('read_at')->count(),
            'license_plan' => License::query()->latest('id')->value('plan') ?? 'free',
        ];

        return $context;
    }

    public function systemPrompt(array $context): string
    {
        $serverLine = $context['server']
            ? sprintf(
                'Serveur actif : %s (%s) — statut %s.',
                $context['server']['name'],
                $context['server']['ip'] ?? '—',
                $context['server']['status'],
            )
            : 'Aucun serveur sélectionné.';

        $doctorLine = $context['doctor']
            ? sprintf(
                'Dernier rapport Doctor : score %d%%, %d finding(s) critique(s).',
                $context['doctor']['score'],
                $context['doctor']['critical_count'],
            )
            : 'Aucun rapport ObiOra Doctor reçu.';

        return <<<PROMPT
Tu es l'assistant ObiOra Panel pour administrer une seedbox Linux (marketplace, services, monitoring Doctor).
Réponds en français, concis, avec des étapes actionnables dans le panel (routes : /plugins, /services, /monitoring, /servers).
Ne propose jamais d'exécuter des commandes shell directement — oriente vers l'UI du panel.
{$serverLine}
{$doctorLine}
Alertes monitoring non lues : {$context['alerts_unread']}.
PROMPT;
    }

    /**
     * @return list<array{label: string, route: string}>
     */
    public function suggestedActions(array $context): array
    {
        $actions = [
            ['label' => 'Ouvrir le monitoring', 'route' => route('monitoring.index')],
            ['label' => 'Marketplace apps', 'route' => route('plugins.index')],
            ['label' => 'Services systemd', 'route' => route('services.index')],
        ];

        if (($context['doctor']['critical_count'] ?? 0) > 0) {
            $actions[] = ['label' => 'Voir alertes monitoring', 'route' => route('monitoring.index')];
        }

        return $actions;
    }
}

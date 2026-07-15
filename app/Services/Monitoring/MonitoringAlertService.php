<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\CrashAnalyzerEvent;
use App\Models\DiagnosticReport;
use App\Models\MonitoringAlert;
use App\Models\Server;
use App\Models\User;
use App\Notifications\CriticalDiagnosticNotification;
use App\Notifications\SslExpiryNotification;
use Illuminate\Support\Facades\Notification;

final class MonitoringAlertService
{
    public function recordCriticalReport(Server $server, DiagnosticReport $report): void
    {
        foreach ($report->critical_findings ?? [] as $finding) {
            $this->createAlert(
                server: $server,
                type: 'diagnostic_critical',
                severity: 'critical',
                title: (string) ($finding['title'] ?? 'Alerte critique'),
                message: (string) ($finding['details'] ?? ''),
                payload: ['module' => $finding['module'] ?? null, 'report_id' => $report->id],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordSslExpiry(Server $server, string $host, int $daysLeft, string $notAfter): void
    {
        $severity = $daysLeft <= 0 ? 'critical' : ($daysLeft <= 30 ? 'warning' : 'info');
        if ($severity === 'info') {
            return;
        }

        $this->createAlert(
            server: $server,
            type: 'ssl_expiry',
            severity: $severity,
            title: "Certificat SSL {$host}",
            message: $daysLeft <= 0
                ? "Certificat expire ou expire aujourd'hui ({$notAfter})."
                : "Certificat expire dans {$daysLeft} jour(s) ({$notAfter}).",
            payload: ['host' => $host, 'days_left' => $daysLeft, 'not_after' => $notAfter],
        );
    }

    public function recordCrashEvent(Server $server, CrashAnalyzerEvent $event): void
    {
        $eventType = (string) $event->event_type;
        $dedupeMinutes = max(5, (int) config('crash_analyzer.alert_dedupe_minutes', 60));

        $duplicate = MonitoringAlert::query()
            ->where('server_id', $server->id)
            ->where('type', 'crash_analyzer')
            ->whereNull('read_at')
            ->where('created_at', '>=', now()->subMinutes($dedupeMinutes))
            ->get()
            ->contains(function (MonitoringAlert $alert) use ($eventType): bool {
                return (string) (($alert->payload ?? [])['event_type'] ?? '') === $eventType;
            });

        if ($duplicate) {
            return;
        }

        $this->createAlert(
            server: $server,
            type: 'crash_analyzer',
            severity: $event->severity === 'critical' ? 'critical' : 'warning',
            title: $event->title,
            message: $event->details ?? '',
            payload: [
                'event_type' => $eventType,
                'event_id' => $event->id,
            ],
        );
    }

    /**
     * Clôt les alertes Crash Analyzer sans récidive récente du même type.
     */
    public function resolveStaleCrashAlerts(): int
    {
        $windowMinutes = max(15, (int) config('crash_analyzer.alert_auto_resolve_minutes', 60));
        $resolved = 0;

        MonitoringAlert::query()
            ->where('type', 'crash_analyzer')
            ->whereNull('read_at')
            ->orderBy('id')
            ->lazy()
            ->each(function (MonitoringAlert $alert) use ($windowMinutes, &$resolved): void {
                $eventType = (string) (($alert->payload ?? [])['event_type'] ?? '');

                $hasRecentEvent = CrashAnalyzerEvent::query()
                    ->where('server_id', $alert->server_id)
                    ->when($eventType !== '', fn ($query) => $query->where('event_type', $eventType))
                    ->where('detected_at', '>=', now()->subMinutes($windowMinutes))
                    ->exists();

                if ($hasRecentEvent) {
                    return;
                }

                if ($alert->created_at !== null && $alert->created_at->gt(now()->subMinutes(15))) {
                    return;
                }

                $alert->forceFill(['read_at' => now()])->save();
                $resolved++;
            });

        return $resolved;
    }

    public function recordInvalidSignature(Server $server): void
    {
        $this->createAlert(
            server: $server,
            type: 'signature_invalid',
            severity: 'warning',
            title: 'Signature rapport invalide',
            message: 'Un rapport Obiora Doctor a ete rejete (signature HMAC invalide).',
            payload: [],
        );
    }

    public function recordServerOffline(Server $server): void
    {
        $recent = MonitoringAlert::query()
            ->where('server_id', $server->id)
            ->where('type', 'server_offline')
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if ($recent) {
            return;
        }

        $this->createAlert(
            server: $server,
            type: 'server_offline',
            severity: 'warning',
            title: "Serveur {$server->name} hors ligne",
            message: 'Le probe ICMP/TCP n a pas repondu.',
            payload: ['server_id' => $server->id],
        );
    }

    public function dispatchPendingEmailAlerts(): int
    {
        $alerts = MonitoringAlert::query()
            ->where('notified', false)
            ->whereIn('severity', ['critical', 'warning'])
            ->where('created_at', '>=', now()->subDay())
            ->with('server')
            ->limit(50)
            ->get();

        $users = User::query()->get();
        if ($users->isEmpty()) {
            return 0;
        }

        $sent = 0;
        foreach ($alerts as $alert) {
            $notification = match ($alert->type) {
                'ssl_expiry' => new SslExpiryNotification($alert),
                default => new CriticalDiagnosticNotification($alert),
            };

            Notification::send($users, $notification);
            $alert->forceFill(['notified' => true])->save();
            $sent++;
        }

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createAlert(
        Server $server,
        string $type,
        string $severity,
        string $title,
        string $message,
        array $payload,
    ): MonitoringAlert {
        return MonitoringAlert::query()->create([
            'server_id' => $server->id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'payload' => $payload,
            'notified' => false,
        ]);
    }
}

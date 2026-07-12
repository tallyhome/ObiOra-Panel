<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\MonitoringIncident;
use Illuminate\Support\Str;

final class MonitoringAlertIntelligenceService
{
    /**
     * @return array{merged: int, escalated: int}
     */
    public function run(): array
    {
        return [
            'merged' => $this->mergeDuplicateServerIncidents(),
            'escalated' => $this->escalateStaleWarnings(),
        ];
    }

    private function mergeDuplicateServerIncidents(): int
    {
        $merged = 0;

        $offlineIncidents = MonitoringIncident::query()
            ->where('status', 'open')
            ->where('resource_type', 'server')
            ->where(function ($q): void {
                $q->where('trigger', 'like', '%offline%')
                    ->orWhere('trigger', 'like', '%hors ligne%')
                    ->orWhere('message', 'like', '%offline%');
            })
            ->get();

        foreach ($offlineIncidents as $offline) {
            $duplicates = MonitoringIncident::query()
                ->where('status', 'open')
                ->where('resource_type', 'server')
                ->where('resource_id', $offline->resource_id)
                ->where('id', '!=', $offline->id)
                ->where(function ($q): void {
                    $q->where('trigger', 'like', '%agent%')
                        ->orWhere('trigger', 'like', '%no data%')
                        ->orWhere('message', 'like', '%agent%');
                })
                ->get();

            foreach ($duplicates as $dup) {
                $offline->forceFill([
                    'message' => trim($offline->message.' [fusionné: '.$dup->trigger.']'),
                    'metadata' => array_merge($offline->metadata ?? [], [
                        'merged_incident_ids' => array_merge(
                            $offline->metadata['merged_incident_ids'] ?? [],
                            [$dup->id],
                        ),
                    ]),
                ])->save();

                $dup->forceFill([
                    'status' => 'resolved',
                    'recovered_at' => now(),
                    'message' => trim(($dup->message ?? '').' [fusionné dans incident #'.$offline->id.']'),
                ])->save();

                $merged++;
            }
        }

        return $merged;
    }

    private function escalateStaleWarnings(): int
    {
        $escalated = 0;
        $warningCutoff = now()->subMinutes(60);
        $criticalCutoff = now()->subMinutes(15);

        $open = MonitoringIncident::query()
            ->where('status', 'open')
            ->where('went_down_at', '<=', $warningCutoff)
            ->get();

        foreach ($open as $incident) {
            $meta = $incident->metadata ?? [];
            $severity = $meta['severity'] ?? 'warning';

            if ($severity === 'warning' && $incident->went_down_at <= $criticalCutoff) {
                $incident->forceFill([
                    'metadata' => array_merge($meta, ['severity' => 'critical']),
                    'message' => Str::startsWith($incident->message, '[ESCALADE]')
                        ? $incident->message
                        : '[ESCALADE] '.$incident->message,
                ])->save();
                $escalated++;
            }
        }

        return $escalated;
    }
}

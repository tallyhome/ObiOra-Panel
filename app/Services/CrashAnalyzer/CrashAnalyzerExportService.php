<?php

declare(strict_types=1);

namespace App\Services\CrashAnalyzer;

use App\Models\CrashAnalyzerEvent;
use App\Models\CrashAnalyzerMetric;
use App\Models\CrashAnalyzerReport;
use App\Models\Server;
use App\Support\CrashAnalyzerTriggerLabels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CrashAnalyzerExportService
{
    public function exportJson(Server $server, Carbon $since): StreamedResponse
    {
        $data = [
            'server' => ['id' => $server->id, 'name' => $server->name, 'hostname' => $server->hostname],
            'exported_at' => now()->toIso8601String(),
            'since' => $since->toIso8601String(),
            'metrics' => $this->metricsPayload($server, $since),
            'events' => $this->eventsPayload($server, $since),
        ];

        return response()->streamDownload(
            fn () => print(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            "crash-analyzer-{$server->id}-".now()->format('Y-m-d_His').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function exportCsv(Server $server, Carbon $since): StreamedResponse
    {
        return response()->streamDownload(function () use ($server, $since) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['sampled_at', 'collector', 'metric', 'value']);

            CrashAnalyzerMetric::query()
                ->where('server_id', $server->id)
                ->where('sampled_at', '>=', $since)
                ->orderBy('sampled_at')
                ->chunk(500, function ($metrics) use ($handle) {
                    foreach ($metrics as $metric) {
                        foreach ($metric->payload ?? [] as $key => $value) {
                            if (is_scalar($value)) {
                                fputcsv($handle, [
                                    $metric->sampled_at?->toIso8601String(),
                                    $metric->collector,
                                    $key,
                                    $value,
                                ]);
                            }
                        }
                    }
                });

            fclose($handle);
        }, "crash-analyzer-{$server->id}-".now()->format('Y-m-d_His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportPdf(Server $server, CrashAnalyzerReport $report): StreamedResponse|\Illuminate\Http\Response
    {
        if ($report->pdf_path && Storage::disk('local')->exists($report->pdf_path)) {
            return response(Storage::disk('local')->get($report->pdf_path), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="crash-report-'.$report->external_id.'.pdf"',
            ]);
        }

        return response($this->renderReportHtml($server, $report), 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="crash-report-'.$report->external_id.'.html"',
        ]);
    }

    public function viewReport(Server $server, CrashAnalyzerReport $report): \Illuminate\Http\Response
    {
        return response($this->renderReportHtml($server, $report, inline: true), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    private function renderReportHtml(Server $server, CrashAnalyzerReport $report, bool $inline = false): string
    {
        $trigger = $report->trigger_type;

        return view($inline ? 'crash-analyzer::exports.report-view' : 'crash-analyzer::exports.report-pdf', [
            'server' => $server,
            'report' => $report,
            'events' => $report->report_json['events'] ?? [],
            'summary' => $report->report_json['metrics_summary'] ?? [],
            'triggerLabel' => CrashAnalyzerTriggerLabels::label($trigger),
            'hints' => CrashAnalyzerTriggerLabels::hints($trigger),
        ])->render();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function metricsPayload(Server $server, Carbon $since): array
    {
        return CrashAnalyzerMetric::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->orderBy('sampled_at')
            ->get()
            ->map(fn (CrashAnalyzerMetric $m) => [
                'collector' => $m->collector,
                'sampled_at' => $m->sampled_at?->toIso8601String(),
                'payload' => $m->payload,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function eventsPayload(Server $server, Carbon $since): array
    {
        return CrashAnalyzerEvent::query()
            ->where('server_id', $server->id)
            ->where('detected_at', '>=', $since)
            ->orderBy('detected_at')
            ->get()
            ->map(fn (CrashAnalyzerEvent $e) => [
                'event_type' => $e->event_type,
                'severity' => $e->severity,
                'title' => $e->title,
                'details' => $e->details,
                'detected_at' => $e->detected_at?->toIso8601String(),
            ])
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CrashAnalyzerReport;
use App\Models\Server;
use App\Services\CrashAnalyzer\CrashAnalyzerExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CrashAnalyzerExportController extends Controller
{
    public function json(Server $server, Request $request, CrashAnalyzerExportService $export): StreamedResponse
    {
        $since = Carbon::parse($request->query('since', now()->subMinutes(
            (int) config('crash_analyzer.history_minutes', 60)
        )->toIso8601String()));

        return $export->exportJson($server, $since);
    }

    public function csv(Server $server, Request $request, CrashAnalyzerExportService $export): StreamedResponse
    {
        $since = Carbon::parse($request->query('since', now()->subMinutes(
            (int) config('crash_analyzer.history_minutes', 60)
        )->toIso8601String()));

        return $export->exportCsv($server, $since);
    }

    public function pdf(Server $server, CrashAnalyzerReport $report, CrashAnalyzerExportService $export): StreamedResponse|\Illuminate\Http\Response
    {
        abort_unless($report->server_id === $server->id, 404);

        return $export->exportPdf($server, $report);
    }

    public function view(Server $server, CrashAnalyzerReport $report, CrashAnalyzerExportService $export): \Illuminate\Http\Response
    {
        abort_unless($report->server_id === $server->id, 404);

        return $export->viewReport($server, $report);
    }
}

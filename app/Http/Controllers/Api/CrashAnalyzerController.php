<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\CrashAnalyzer\CrashAnalyzerIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CrashAnalyzerController extends Controller
{
    public function storeMetrics(Request $request, Server $server, CrashAnalyzerIngestService $ingest): JsonResponse
    {
        if (! $this->authorizeAgent($request, $server)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        try {
            $count = $ingest->ingestMetrics($server, $payload);

            return response()->json(['ok' => true, 'metrics_ingested' => $count]);
        } catch (Throwable $e) {
            Log::error('Crash Analyzer metrics ingest failed', [
                'server_id' => $server->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Ingest failed'], 500);
        }
    }

    public function storeReport(Request $request, Server $server, CrashAnalyzerIngestService $ingest): JsonResponse
    {
        if (! $this->authorizeAgent($request, $server)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        if (! isset($payload['report_json'])) {
            return response()->json(['error' => 'report_json required'], 422);
        }

        try {
            $report = $ingest->ingestReport($server, $payload);

            return response()->json(['ok' => true, 'id' => $report->id]);
        } catch (Throwable $e) {
            Log::error('Crash Analyzer report ingest failed', [
                'server_id' => $server->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Report ingest failed'], 500);
        }
    }

    public function dashboard(Server $server, \App\Services\CrashAnalyzer\CrashAnalyzerMetricsService $metrics): JsonResponse
    {
        $minutes = (int) request()->query('minutes', config('crash_analyzer.history_minutes', 60));

        return response()->json($metrics->dashboardData($server, $minutes));
    }

    private function authorizeAgent(Request $request, Server $server): bool
    {
        $agentServer = $request->attributes->get('agent_server');

        return $agentServer instanceof Server && $agentServer->id === $server->id;
    }
}

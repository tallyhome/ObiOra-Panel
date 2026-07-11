<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\CrashHunter\CrashHunterIngestService;
use App\Services\CrashHunter\CrashHunterMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CrashHunterController extends Controller
{
    public function storeMetrics(Request $request, Server $server, CrashHunterIngestService $ingest): JsonResponse
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
            Log::error('CrashHunter metrics ingest failed', ['server_id' => $server->id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Ingest failed'], 500);
        }
    }

    public function storeSnapshots(Request $request, Server $server, CrashHunterIngestService $ingest): JsonResponse
    {
        if (! $this->authorizeAgent($request, $server)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        try {
            $count = $ingest->ingestSnapshots($server, $payload);

            return response()->json(['ok' => true, 'snapshots_ingested' => $count]);
        } catch (Throwable $e) {
            Log::error('CrashHunter snapshots ingest failed', ['server_id' => $server->id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Ingest failed'], 500);
        }
    }

    public function storeWitness(Request $request, Server $server, CrashHunterIngestService $ingest): JsonResponse
    {
        if (! $this->authorizeAgent($request, $server)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        try {
            $record = $ingest->ingestWitness($server, $payload);

            return response()->json(['ok' => true, 'status' => $record->status]);
        } catch (Throwable $e) {
            Log::error('CrashHunter witness ingest failed', ['server_id' => $server->id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Witness ingest failed'], 500);
        }
    }

    public function storeIncident(Request $request, Server $server, CrashHunterIngestService $ingest): JsonResponse
    {
        if (! $this->authorizeAgent($request, $server)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        try {
            $incident = $ingest->ingestIncident($server, $payload);

            return response()->json(['ok' => true, 'id' => $incident->id]);
        } catch (Throwable $e) {
            Log::error('CrashHunter incident ingest failed', ['server_id' => $server->id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Incident ingest failed'], 500);
        }
    }

    public function storeReport(Request $request, Server $server, CrashHunterIngestService $ingest): JsonResponse
    {
        if (! $this->authorizeAgent($request, $server)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        if (! isset($payload['report_json']) && ! isset($payload['report_id'])) {
            return response()->json(['error' => 'report_json required'], 422);
        }

        try {
            $report = $ingest->ingestReport($server, $payload);

            return response()->json(['ok' => true, 'id' => $report->id]);
        } catch (Throwable $e) {
            Log::error('CrashHunter report ingest failed', ['server_id' => $server->id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Report ingest failed'], 500);
        }
    }

    public function storeEvents(Request $request, Server $server, CrashHunterIngestService $ingest): JsonResponse
    {
        if (! $this->authorizeAgent($request, $server)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        try {
            $count = $ingest->ingestEvents($server, $payload);

            return response()->json(['ok' => true, 'events_ingested' => $count]);
        } catch (Throwable $e) {
            Log::error('CrashHunter events ingest failed', ['server_id' => $server->id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Events ingest failed'], 500);
        }
    }

    public function dashboard(Server $server, CrashHunterMetricsService $metrics): JsonResponse
    {
        $minutes = (int) request()->query('minutes', config('crash_hunter.history_minutes', 60));

        return response()->json($metrics->dashboardData($server, $minutes));
    }

    private function authorizeAgent(Request $request, Server $server): bool
    {
        $agentServer = $request->attributes->get('agent_server');

        return $agentServer instanceof Server && $agentServer->id === $server->id;
    }
}

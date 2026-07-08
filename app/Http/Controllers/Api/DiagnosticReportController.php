<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Diagnostics\DiagnosticReportManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DiagnosticReportController extends Controller
{
    public function store(Request $request, Server $server, DiagnosticReportManager $manager): JsonResponse
    {
        $agentServer = $request->attributes->get('agent_server');
        if (! $agentServer instanceof Server || $agentServer->id !== $server->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        if (! isset($payload['score'], $payload['results'])) {
            return response()->json(['error' => 'Invalid report payload'], 422);
        }

        try {
            $report = $manager->ingest($server, $payload);

            return response()->json([
                'ok' => true,
                'id' => $report->id,
                'score' => $report->score,
                'status' => $report->status,
                'signature_verified' => $report->signature_verified,
            ]);
        } catch (Throwable $e) {
            Log::error('Diagnostic report ingest failed', [
                'server_id' => $server->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Report ingest failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Server Error',
            ], 500);
        }
    }

    public function heartbeat(Request $request, Server $server, DiagnosticReportManager $manager): JsonResponse
    {
        $agentServer = $request->attributes->get('agent_server');
        if (! $agentServer instanceof Server || $agentServer->id !== $server->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        /** @var array<string, mixed> $metrics */
        $metrics = $request->json()->all();
        $manager->heartbeat($server, $metrics);

        return response()->json(['ok' => true]);
    }

    public function latest(Server $server): JsonResponse
    {
        $report = $server->diagnosticReports()->latest('generated_at')->first();
        if ($report === null) {
            return response()->json(['error' => 'no reports'], 404);
        }

        return response()->json($report->report_json);
    }

    public function index(Server $server): JsonResponse
    {
        $reports = $server->diagnosticReports()
            ->latest('generated_at')
            ->limit(20)
            ->get(['id', 'score', 'status', 'hostname', 'generated_at', 'critical_findings']);

        return response()->json(['reports' => $reports]);
    }
}

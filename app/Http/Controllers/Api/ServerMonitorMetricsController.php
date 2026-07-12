<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Monitoring\ServerMonitorMetricsIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ServerMonitorMetricsController extends Controller
{
    public function store(Request $request, Server $server, ServerMonitorMetricsIngestService $ingest): JsonResponse
    {
        if (! $this->authorizeAgent($request, $server)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        if (! isset($payload['schema_version'])) {
            $payload['schema_version'] = 1;
        }

        try {
            $result = $ingest->ingest($server, $payload);

            return response()->json($result);
        } catch (Throwable $exception) {
            Log::error('Monitor metrics ingest failed', [
                'server_id' => $server->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Ingest failed'], 500);
        }
    }

    private function authorizeAgent(Request $request, Server $server): bool
    {
        $agentServer = $request->attributes->get('agent_server');

        return $agentServer instanceof Server && $agentServer->id === $server->id;
    }
}

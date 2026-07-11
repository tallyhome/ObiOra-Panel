<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Diagnostics\RemoteAgentControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CrashHunterAgentController extends Controller
{
    public function listAgents(Request $request, Server $server, RemoteAgentControlService $control): JsonResponse
    {
        abort_unless($request->user()?->can('servers.manage'), 403);

        $validated = $request->validate([
            'ssh_host' => ['required', 'string', 'max:255'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_user' => ['nullable', 'string', 'max:64'],
        ]);

        $result = $control->listAgents(
            $server,
            $validated['ssh_host'],
            (int) ($validated['ssh_port'] ?? 22),
            (string) ($validated['ssh_user'] ?? 'root'),
        );

        return response()->json($result);
    }

    public function stopAll(Request $request, Server $server, RemoteAgentControlService $control): JsonResponse
    {
        abort_unless($request->user()?->can('servers.manage'), 403);

        $validated = $request->validate([
            'ssh_host' => ['required', 'string', 'max:255'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_user' => ['nullable', 'string', 'max:64'],
        ]);

        $result = $control->stopAllDiagnostics(
            $server,
            $validated['ssh_host'],
            (int) ($validated['ssh_port'] ?? 22),
            (string) ($validated['ssh_user'] ?? 'root'),
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}

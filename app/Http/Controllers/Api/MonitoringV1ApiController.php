<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlertPolicy;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\MonitoringIncident;
use App\Models\Server;
use App\Services\Monitoring\MaintenanceWindowService;
use App\Services\Monitoring\MonitorImportExportService;
use App\Services\Monitoring\MonitorRunnerService;
use App\Services\Monitoring\ServerMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MonitoringV1ApiController extends Controller
{
    public function servers(): JsonResponse
    {
        $rows = Server::query()
            ->where('is_master', false)
            ->orderBy('name')
            ->get()
            ->map(fn (Server $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'status' => $s->status->value,
                'os_name' => $s->os_name,
                'last_seen_at' => $s->last_seen_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function serverMetrics(Server $server, Request $request, ServerMetricsService $metrics): JsonResponse
    {
        $preset = (string) $request->query('preset', '24h');
        $range = $metrics->resolvePreset($preset);

        return response()->json($metrics->dashboard($server, $range['from'], $range['to'], $range['resolution']));
    }

    public function monitors(): JsonResponse
    {
        $rows = Monitor::query()->orderBy('name')->get()->map(fn (Monitor $m) => $this->serializeMonitor($m));

        return response()->json(['data' => $rows]);
    }

    public function storeMonitor(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'target' => ['required', 'string'],
            'port' => ['nullable', 'integer'],
            'keyword' => ['nullable', 'string'],
            'interval_seconds' => ['nullable', 'integer'],
            'tags' => ['nullable', 'array'],
        ]);

        $monitor = Monitor::query()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'target' => $data['target'],
            'port' => $data['port'] ?? null,
            'keyword' => $data['keyword'] ?? null,
            'interval_seconds' => $data['interval_seconds'] ?? 300,
            'tags' => $data['tags'] ?? [],
        ]);

        return response()->json(['data' => $this->serializeMonitor($monitor)], 201);
    }

    public function monitorChecks(Monitor $monitor): JsonResponse
    {
        $checks = $monitor->checks()
            ->orderByDesc('checked_at')
            ->limit(200)
            ->get()
            ->map(fn ($c) => [
                'status' => $c->status,
                'response_ms' => $c->response_ms,
                'metrics' => $c->metrics,
                'checked_at' => $c->checked_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $checks]);
    }

    public function incidents(): JsonResponse
    {
        $rows = MonitoringIncident::query()
            ->orderByDesc('went_down_at')
            ->limit(100)
            ->get()
            ->map(fn (MonitoringIncident $i) => [
                'id' => $i->id,
                'resource_type' => $i->resource_type,
                'resource_name' => $i->resource_name,
                'trigger' => $i->trigger,
                'message' => $i->message,
                'status' => $i->status,
                'went_down_at' => $i->went_down_at?->toIso8601String(),
                'recovered_at' => $i->recovered_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function alertPolicies(): JsonResponse
    {
        $rows = AlertPolicy::query()->orderBy('name')->get()->map(fn (AlertPolicy $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'metric' => $p->metric,
            'operator' => $p->operator instanceof \App\Enums\AlertPolicyOperator ? $p->operator->value : $p->operator,
            'value' => $p->value,
            'is_enabled' => $p->is_enabled,
        ]);

        return response()->json(['data' => $rows]);
    }

    public function exportMonitors(MonitorImportExportService $importExport): JsonResponse
    {
        $this->authorizeManage();

        return response()->json($importExport->exportJson());
    }

    public function importMonitors(Request $request, MonitorImportExportService $importExport): JsonResponse
    {
        $this->authorizeManage();

        $payload = $request->validate([
            'monitors' => ['required', 'array'],
            'monitors.*.name' => ['required', 'string'],
            'monitors.*.type' => ['required', 'string'],
            'monitors.*.target' => ['required', 'string'],
        ]);

        $result = $importExport->importJson($payload);

        return response()->json($result);
    }

    public function maintenanceWindows(MaintenanceWindowService $service): JsonResponse
    {
        $rows = $service->upcomingAndActive()->map(fn (MaintenanceWindow $window) => [
            'id' => $window->id,
            'resource_type' => $window->resource_type,
            'resource_ids' => $window->resource_ids ?? [],
            'starts_at' => $window->starts_at?->toIso8601String(),
            'ends_at' => $window->ends_at?->toIso8601String(),
            'note' => $window->note,
            'active' => $window->isActive(),
        ]);

        return response()->json(['data' => $rows]);
    }

    public function storeMaintenance(Request $request, MaintenanceWindowService $service): JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'resource_type' => ['required', 'in:all,server,monitor'],
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => ['integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $window = $service->schedule(
            resourceType: $data['resource_type'],
            resourceIds: $data['resource_ids'] ?? null,
            startsAt: \Illuminate\Support\Carbon::parse($data['starts_at']),
            endsAt: \Illuminate\Support\Carbon::parse($data['ends_at']),
            note: $data['note'] ?? null,
            creator: auth()->user(),
        );

        return response()->json(['data' => [
            'id' => $window->id,
            'resource_type' => $window->resource_type,
            'starts_at' => $window->starts_at?->toIso8601String(),
            'ends_at' => $window->ends_at?->toIso8601String(),
        ]], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMonitor(Monitor $monitor): array
    {
        return [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'type' => $monitor->type->value,
            'target' => $monitor->target,
            'port' => $monitor->port,
            'interval_seconds' => $monitor->interval_seconds,
            'tags' => $monitor->tags ?? [],
            'last_status' => $monitor->last_status,
            'last_checked_at' => $monitor->last_checked_at?->toIso8601String(),
        ];
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('monitoring.manage'), 403);
    }
}

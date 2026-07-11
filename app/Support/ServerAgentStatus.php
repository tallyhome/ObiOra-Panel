<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ServerStatus;
use App\Models\CrashAnalyzerMetric;
use App\Models\CrashHunterMetric;
use App\Models\DiagnosticReport;
use App\Models\Server;

final class ServerAgentStatus
{
    /**
     * @return array{slave: bool, doctor: bool, crash: bool, crash_hunter: bool, any: bool}
     */
    public function flags(Server $server): array
    {
        $meta = $server->metadata ?? [];
        $doctorDeploy = is_array($meta['doctor_deploy'] ?? null) ? $meta['doctor_deploy'] : [];
        $components = is_array($doctorDeploy['components'] ?? null) ? $doctorDeploy['components'] : [];

        $slave = (bool) ($meta['agent_installed'] ?? false)
            || isset($meta['slave_deploy'])
            || $server->status === ServerStatus::Online;

        $doctor = isset($meta['doctor_deploy'])
            || DiagnosticReport::query()->where('server_id', $server->id)->exists();

        $crash = in_array('crash_analyzer', $components, true)
            || in_array('doctor_suite', $components, true)
            || CrashAnalyzerMetric::query()->where('server_id', $server->id)->exists();

        $crashHunter = in_array('crash_hunter', $components, true)
            || isset($meta['crash_hunter'])
            || CrashHunterMetric::query()->where('server_id', $server->id)->exists();

        return [
            'slave' => $slave,
            'doctor' => $doctor,
            'crash' => $crash,
            'crash_hunter' => $crashHunter,
            'any' => $slave || $doctor || $crash || $crashHunter,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\DiagnosticReport;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Server;
use App\Models\ServerMetricSample;
use Illuminate\Support\Str;

final class PrometheusMetricsExporter
{
    /**
     * @return list<string>
     */
    public function export(): array
    {
        $lines = [
            '# HELP obiora_build_info ObiOra Panel version',
            '# TYPE obiora_build_info gauge',
            'obiora_build_info{version="'.Str::of((string) config('obiora.version', '0'))->replace('"', '').'"} 1',
        ];

        foreach ($this->serverMetrics() as $line) {
            $lines[] = $line;
        }

        foreach ($this->monitorMetrics() as $line) {
            $lines[] = $line;
        }

        return $lines;
    }

    public function render(): string
    {
        return implode("\n", $this->export())."\n";
    }

    /**
     * @return list<string>
     */
    private function serverMetrics(): array
    {
        $lines = [
            '# HELP obiora_server_up Serveur joignable (1=online)',
            '# TYPE obiora_server_up gauge',
            '# HELP obiora_server_cpu_percent CPU usage percent',
            '# TYPE obiora_server_cpu_percent gauge',
            '# HELP obiora_server_memory_percent Memory usage percent',
            '# TYPE obiora_server_memory_percent gauge',
            '# HELP obiora_server_disk_percent Disk usage percent',
            '# TYPE obiora_server_disk_percent gauge',
            '# HELP obiora_server_cpu_steal_percent CPU steal percent',
            '# TYPE obiora_server_cpu_steal_percent gauge',
            '# HELP obiora_doctor_score Dernier score Doctor (0-100)',
            '# TYPE obiora_doctor_score gauge',
        ];

        Server::query()
            ->where('is_master', false)
            ->orderBy('id')
            ->each(function (Server $server) use (&$lines): void {
                $labels = $this->labels($server->id, $server->name);
                $up = in_array($server->status->value, ['online', 'degraded'], true) ? 1 : 0;
                $lines[] = "obiora_server_up{$labels} {$up}";

                $sample = ServerMetricSample::query()
                    ->where('server_id', $server->id)
                    ->orderByDesc('sampled_at')
                    ->first();

                if ($sample !== null) {
                    $lines[] = "obiora_server_cpu_percent{$labels} ".$this->num($sample->cpu_percent);
                    $lines[] = "obiora_server_memory_percent{$labels} ".$this->num($sample->memory_percent);
                    $lines[] = "obiora_server_disk_percent{$labels} ".$this->num($sample->disk_percent);
                    $lines[] = "obiora_server_cpu_steal_percent{$labels} ".$this->num($sample->cpu_steal_percent);
                }

                $score = DiagnosticReport::query()
                    ->where('server_id', $server->id)
                    ->orderByDesc('generated_at')
                    ->value('score');

                if ($score !== null) {
                    $lines[] = "obiora_doctor_score{$labels} ".$this->num((float) $score);
                }
            });

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function monitorMetrics(): array
    {
        $lines = [
            '# HELP obiora_monitor_up Moniteur externe up (1=ok)',
            '# TYPE obiora_monitor_up gauge',
            '# HELP obiora_monitor_response_ms Dernière réponse ms',
            '# TYPE obiora_monitor_response_ms gauge',
        ];

        Monitor::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (Monitor $monitor) use (&$lines): void {
                $labels = $this->labels($monitor->id, $monitor->name, 'monitor');
                $check = MonitorCheck::query()
                    ->where('monitor_id', $monitor->id)
                    ->orderByDesc('checked_at')
                    ->first();

                $up = ($check?->status ?? 'down') === 'up' ? 1 : 0;
                $lines[] = "obiora_monitor_up{$labels} {$up}";

                if ($check?->response_ms !== null) {
                    $lines[] = "obiora_monitor_response_ms{$labels} ".(int) $check->response_ms;
                }
            });

        return $lines;
    }

    private function labels(int $id, string $name, string $resource = 'server'): string
    {
        $safeName = Str::of($name)->replace('\\', '\\\\')->replace('"', '\\"')->limit(64, '');

        return '{resource="'.$resource.'",id="'.$id.'",name="'.$safeName.'"}';
    }

    private function num(?float $value): string
    {
        if ($value === null) {
            return '0';
        }

        return (string) round($value, 4);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\Monitor;

final class MonitorImportExportService
{
    /**
     * @return array{version: int, exported_at: string, monitors: list<array<string, mixed>>}
     */
    public function exportJson(): array
    {
        $monitors = Monitor::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Monitor $m) => [
                'name' => $m->name,
                'type' => $m->type->value,
                'target' => $m->target,
                'port' => $m->port,
                'keyword' => $m->keyword,
                'keyword_present' => $m->keyword_present,
                'interval_seconds' => $m->interval_seconds,
                'tags' => $m->tags ?? [],
                'is_active' => $m->is_active,
            ])
            ->all();

        return [
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'monitors' => $monitors,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{created: int, skipped: int}
     */
    public function importJson(array $payload): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($payload['monitors'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = (string) ($row['name'] ?? '');

            if ($name === '' || Monitor::query()->where('name', $name)->exists()) {
                $skipped++;

                continue;
            }

            Monitor::query()->create([
                'name' => $name,
                'type' => (string) ($row['type'] ?? 'https'),
                'target' => (string) ($row['target'] ?? ''),
                'port' => $row['port'] ?? null,
                'keyword' => $row['keyword'] ?? null,
                'keyword_present' => (bool) ($row['keyword_present'] ?? true),
                'interval_seconds' => (int) ($row['interval_seconds'] ?? 300),
                'tags' => is_array($row['tags'] ?? null) ? $row['tags'] : [],
                'is_active' => (bool) ($row['is_active'] ?? true),
            ]);

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function exportCsv(): string
    {
        $lines = ['name,type,target,port,keyword,interval,tags'];

        foreach (Monitor::query()->orderBy('name')->get() as $monitor) {
            $lines[] = implode(',', [
                $this->csvEscape($monitor->name),
                $monitor->type->value,
                $this->csvEscape($monitor->target),
                $monitor->port ?? '',
                $this->csvEscape($monitor->keyword ?? ''),
                $monitor->interval_seconds,
                $this->csvEscape(implode('|', $monitor->tags ?? [])),
            ]);
        }

        return implode("\n", $lines);
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"')) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}

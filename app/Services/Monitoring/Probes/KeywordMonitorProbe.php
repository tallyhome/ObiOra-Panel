<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Probes;

use App\Models\Monitor;
use Illuminate\Support\Facades\Http;

final class KeywordMonitorProbe implements MonitorProbe
{
    public function check(Monitor $monitor): MonitorCheckResult
    {
        $url = $this->normalizeUrl($monitor->target);
        $keyword = (string) $monitor->keyword;
        $start = microtime(true);

        if ($keyword === '') {
            return new MonitorCheckResult('down', null, [], 'Mot-clé requis');
        }

        try {
            $response = Http::withOptions([
                'verify' => false,
                'allow_redirects' => true,
                'connect_timeout' => 10,
                'timeout' => 20,
            ])->get($url);

            $responseMs = (int) round((microtime(true) - $start) * 1000);
            $body = $response->body();
            $found = str_contains($body, $keyword);
            $expected = (bool) $monitor->keyword_present;
            $up = $found === $expected;

            return new MonitorCheckResult(
                status: $up ? 'up' : 'down',
                responseMs: $responseMs,
                metrics: [
                    'http_code' => $response->status(),
                    'keyword_found' => $found,
                    'keyword_present' => $expected,
                ],
                error: $up ? null : ($expected ? 'Mot-clé absent' : 'Mot-clé présent (indésirable)'),
            );
        } catch (\Throwable $exception) {
            $responseMs = (int) round((microtime(true) - $start) * 1000);

            return new MonitorCheckResult('down', $responseMs, [], $exception->getMessage());
        }
    }

    private function normalizeUrl(string $target): string
    {
        if (preg_match('#^https?://#i', $target)) {
            return $target;
        }

        return "https://{$target}";
    }
}

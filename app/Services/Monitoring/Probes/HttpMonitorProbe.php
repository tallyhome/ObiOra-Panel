<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Probes;

use App\Models\Monitor;
use Illuminate\Support\Facades\Http;

final class HttpMonitorProbe implements MonitorProbe
{
    public function __construct(
        private readonly bool $verifySsl = true,
    ) {}

    public function check(Monitor $monitor): MonitorCheckResult
    {
        $url = $this->normalizeUrl($monitor->target);
        $start = microtime(true);

        try {
            $response = Http::withOptions([
                'verify' => $this->verifySsl,
                'allow_redirects' => true,
                'connect_timeout' => 10,
                'timeout' => 20,
            ])->get($url);

            $responseMs = (int) round((microtime(true) - $start) * 1000);
            $httpCode = $response->status();
            $up = $httpCode >= 200 && $httpCode < 400;

            $metrics = [
                'http_code' => $httpCode,
                'ttfb_ms' => $responseMs,
            ];

            if ($this->verifySsl) {
                $metrics['ssl_days_remaining'] = $this->sslDaysRemaining($url);
            }

            return new MonitorCheckResult(
                status: $up ? 'up' : 'down',
                responseMs: $responseMs,
                metrics: $metrics,
                error: $up ? null : "HTTP {$httpCode}",
            );
        } catch (\Throwable $exception) {
            $responseMs = (int) round((microtime(true) - $start) * 1000);

            return new MonitorCheckResult(
                status: 'down',
                responseMs: $responseMs,
                metrics: ['http_code' => null],
                error: $exception->getMessage(),
            );
        }
    }

    private function normalizeUrl(string $target): string
    {
        if (preg_match('#^https?://#i', $target)) {
            return $target;
        }

        $scheme = $this->verifySsl ? 'https' : 'http';

        return "{$scheme}://{$target}";
    }

    private function sslDaysRemaining(string $url): ?int
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $port = (int) (parse_url($url, PHP_URL_PORT) ?: 443);
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($client === false) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (! is_resource($cert) && ! is_object($cert)) {
            return null;
        }

        $parsed = openssl_x509_parse($cert);

        if (! is_array($parsed) || ! isset($parsed['validTo_time_t'])) {
            return null;
        }

        return (int) floor(($parsed['validTo_time_t'] - time()) / 86400);
    }
}

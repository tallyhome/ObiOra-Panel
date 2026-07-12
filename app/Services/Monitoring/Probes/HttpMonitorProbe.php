<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Probes;

use App\Models\Monitor;

final class HttpMonitorProbe implements MonitorProbe
{
    public function __construct(
        private readonly bool $verifySsl = true,
    ) {}

    public function check(Monitor $monitor): MonitorCheckResult
    {
        $url = $this->normalizeUrl($monitor->target);

        if (app()->environment('testing')) {
            return $this->checkWithHttpFacade($url);
        }

        if (function_exists('curl_init')) {
            return $this->checkWithCurl($url);
        }

        return $this->checkWithHttpFacade($url);
    }

    private function checkWithCurl(string $url): MonitorCheckResult
    {
        $start = microtime(true);
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => 'ObiOra-Monitor/1.0',
            CURLOPT_HEADER => false,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $errno !== 0 ? curl_error($ch) : null;
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $dnsSec = (float) curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
        $connectSec = (float) curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        $startTransferSec = (float) curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);
        $totalSec = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        $responseMs = (int) round($totalSec * 1000);
        $dnsMs = (int) round($dnsSec * 1000);
        $tcpMs = (int) round(max(0, $connectSec - $dnsSec) * 1000);
        $ttfbMs = (int) round(max(0, $startTransferSec - $connectSec) * 1000);

        if ($body === false || $error !== null) {
            return new MonitorCheckResult(
                status: 'down',
                responseMs: (int) round((microtime(true) - $start) * 1000),
                metrics: [
                    'dns_ms' => $dnsMs,
                    'tcp_connect_ms' => $tcpMs,
                    'ttfb_ms' => $ttfbMs,
                    'http_code' => $httpCode ?: null,
                ],
                error: $error ?? 'curl error',
            );
        }

        $up = $httpCode >= 200 && $httpCode < 400;

        $metrics = [
            'http_code' => $httpCode,
            'dns_ms' => $dnsMs,
            'tcp_connect_ms' => $tcpMs,
            'ttfb_ms' => $ttfbMs,
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
    }

    private function checkWithHttpFacade(string $url): MonitorCheckResult
    {
        $start = microtime(true);

        try {
            $response = \Illuminate\Support\Facades\Http::withOptions([
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
            return new MonitorCheckResult(
                status: 'down',
                responseMs: (int) round((microtime(true) - $start) * 1000),
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

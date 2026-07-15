<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Monitoring\PrometheusMetricsExporter;
use Illuminate\Http\Response;

final class PrometheusMetricsController extends Controller
{
    public function __invoke(PrometheusMetricsExporter $exporter): Response
    {
        return response($exporter->render(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store',
        ]);
    }
}

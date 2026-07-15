<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticatePrometheusToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('monitoring.prometheus.enabled', false)) {
            abort(404);
        }

        $expected = (string) config('monitoring.prometheus.token', '');

        if ($expected === '') {
            abort(503, 'Prometheus export disabled (token missing)');
        }

        $provided = $request->bearerToken()
            ?? $request->query('token')
            ?? $request->header('X-Prometheus-Token');

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            abort(401, 'Invalid Prometheus token');
        }

        return $next($request);
    }
}

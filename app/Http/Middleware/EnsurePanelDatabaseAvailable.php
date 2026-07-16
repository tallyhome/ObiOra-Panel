<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\PanelInfrastructure;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Avant la session — évite 500 opaque (MariaDB/Redis/disque) après reboot ou nuit.
 */
final class EnsurePanelDatabaseAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up', 'panel-health', 'install/*')) {
            return $next($request);
        }

        PanelInfrastructure::fallbackCacheOffRedis();

        $disk = PanelInfrastructure::diskStatus();
        if (! $disk['ok']) {
            PanelInfrastructure::reclaimDiskIfCritical();
            $disk = PanelInfrastructure::diskStatus();
        }

        if (! PanelInfrastructure::isReady() || ! $disk['ok']) {
            return response()->view('errors.panel-unavailable', [
                'diagnostics' => PanelInfrastructure::diagnostics(true),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $next($request);
    }
}

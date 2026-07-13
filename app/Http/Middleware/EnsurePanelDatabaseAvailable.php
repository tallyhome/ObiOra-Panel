<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\PanelInfrastructure;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before session middleware — avoids 500 when MariaDB/Redis are still starting after reboot.
 */
final class EnsurePanelDatabaseAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up') || $request->is('install/*')) {
            return $next($request);
        }

        if (! PanelInfrastructure::isReady()) {
            return response()->view('errors.panel-unavailable', [], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $next($request);
    }
}

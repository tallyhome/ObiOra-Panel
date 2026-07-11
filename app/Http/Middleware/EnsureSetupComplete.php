<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\PanelDatabase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PanelDatabase::isAvailable()) {
            return response()->view('errors.panel-unavailable', [], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $hasAdmin = User::query()->exists();

        if (! $hasAdmin && ! $request->routeIs('setup')) {
            return redirect()->route('setup');
        }

        if ($hasAdmin && $request->routeIs('setup')) {
            return auth()->check()
                ? redirect()->route('dashboard')
                : redirect()->route('login');
        }

        return $next($request);
    }
}

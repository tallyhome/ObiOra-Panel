<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateSiteApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('obiora.site_api.key');
        if (! $key) {
            return response()->json(['error' => 'Site API disabled'], 503);
        }

        $auth = (string) $request->header('Authorization', '');
        if (! str_starts_with($auth, 'Bearer ') || substr($auth, 7) !== $key) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

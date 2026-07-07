<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Server;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateAgentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = (string) $request->header('Authorization', '');
        if (! str_starts_with($auth, 'Bearer ')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = substr($auth, 7);
        if ($token === '') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $server = Server::query()
            ->where('agent_token', $token)
            ->first();

        if ($server === null) {
            return response()->json(['error' => 'Invalid agent token'], 401);
        }

        $request->attributes->set('agent_server', $server);

        return $next($request);
    }
}

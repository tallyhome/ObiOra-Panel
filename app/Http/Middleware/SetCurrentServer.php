<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Server;
use App\Services\Core\MasterServerSync;
use App\Support\PanelDatabase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetCurrentServer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && PanelDatabase::isAvailable()) {
            if (! Server::query()->where('is_master', true)->exists()) {
                app(MasterServerSync::class)->ensureIfMissing();
            }

            $serverId = session('current_server_id');

            if ($serverId && Server::query()->whereKey($serverId)->exists()) {
                $request->attributes->set('current_server', Server::find($serverId));
            } else {
                $master = Server::query()->where('is_master', true)->first()
                    ?? Server::query()->first();

                if ($master) {
                    session(['current_server_id' => $master->id]);
                    $request->attributes->set('current_server', $master);
                }
            }
        }

        return $next($request);
    }
}

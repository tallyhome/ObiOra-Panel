<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSetupComplete;
use App\Http\Middleware\EnsureDemoNotExpired;
use App\Http\Middleware\SetCurrentServer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            \App\Http\Middleware\EnsurePanelDatabaseAvailable::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'setup' => EnsureSetupComplete::class,
            'demo.active' => EnsureDemoNotExpired::class,
            'server' => SetCurrentServer::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
            'agent.token' => \App\Http\Middleware\AuthenticateAgentToken::class,
            'prometheus.token' => \App\Http\Middleware\AuthenticatePrometheusToken::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->priority([
            \App\Http\Middleware\EnsurePanelDatabaseAvailable::class,
            EnsureSetupComplete::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\SetLocale::class,
            EnsureDemoNotExpired::class,
            SetCurrentServer::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->preventRequestsDuringMaintenance(except: [
            'api/*',
            'up',
            'metrics',
            'install/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $panelUnavailable = static function (Request $request): bool {
            return ! $request->is('api/*')
                && ! $request->expectsJson()
                && ! $request->is('up')
                && ! $request->is('install/*');
        };

        $renderUnavailable = static function (Request $request) use ($panelUnavailable) {
            if (! $panelUnavailable($request)) {
                return null;
            }

            \App\Support\PanelInfrastructure::fallbackCacheOffRedis();
            if (! \App\Support\PanelInfrastructure::diskStatus()['ok']) {
                \App\Support\PanelInfrastructure::reclaimDiskIfCritical();
            }

            return response()->view('errors.panel-unavailable', [
                'diagnostics' => \App\Support\PanelInfrastructure::diagnostics(true),
            ], 503);
        };

        $exceptions->render(function (QueryException $e, Request $request) use ($renderUnavailable) {
            return $renderUnavailable($request);
        });

        $exceptions->render(function (\PDOException $e, Request $request) use ($renderUnavailable) {
            return $renderUnavailable($request);
        });

        if (class_exists(\RedisException::class)) {
            $exceptions->render(function (\RedisException $e, Request $request) use ($renderUnavailable) {
                return $renderUnavailable($request);
            });
        }

        $redisConnectionException = 'Illuminate\\Redis\\Connections\\ConnectionException';
        if (class_exists($redisConnectionException)) {
            $exceptions->render(function ($e, Request $request) use ($renderUnavailable, $redisConnectionException) {
                if (! $e instanceof $redisConnectionException) {
                    return null;
                }

                return $renderUnavailable($request);
            });
        }

        $exceptions->render(function (\Throwable $e, Request $request) use ($renderUnavailable) {
            if (! \App\Support\PanelInfrastructure::isDiskSpaceException($e)) {
                return null;
            }

            return $renderUnavailable($request);
        });

        $exceptions->render(function (\Illuminate\Broadcasting\BroadcastException $e, Request $request) {
            \Illuminate\Support\Facades\Log::warning('BroadcastException swallowed', [
                'message' => $e->getMessage(),
                'path' => $request->path(),
            ]);
            \App\Support\Realtime::resetReachableCache();

            if ($request->expectsJson() || $request->is('livewire/*') || $request->is('api/*')) {
                return response()->json(['ok' => true, 'realtime' => false], 200);
            }

            if (auth()->check()) {
                return redirect()->route('dashboard');
            }

            return redirect()->route('login');
        });
    })->create();

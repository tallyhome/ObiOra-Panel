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

        $exceptions->render(function (QueryException $e, Request $request) use ($panelUnavailable) {
            if ($panelUnavailable($request)) {
                return response()->view('errors.panel-unavailable', [
                    'diagnostics' => \App\Support\PanelInfrastructure::diagnostics(true),
                ], 503);
            }

            return null;
        });

        $exceptions->render(function (\PDOException $e, Request $request) use ($panelUnavailable) {
            if ($panelUnavailable($request)) {
                return response()->view('errors.panel-unavailable', [
                    'diagnostics' => \App\Support\PanelInfrastructure::diagnostics(true),
                ], 503);
            }

            return null;
        });

        if (class_exists(\RedisException::class)) {
            $exceptions->render(function (\RedisException $e, Request $request) use ($panelUnavailable) {
                if ($panelUnavailable($request)) {
                    return response()->view('errors.panel-unavailable', [
                        'diagnostics' => \App\Support\PanelInfrastructure::diagnostics(true),
                    ], 503);
                }

                return null;
            });
        }

        $redisConnectionException = 'Illuminate\\Redis\\Connections\\ConnectionException';
        if (class_exists($redisConnectionException)) {
            $exceptions->render(function ($e, Request $request) use ($panelUnavailable, $redisConnectionException) {
                if (! $e instanceof $redisConnectionException) {
                    return null;
                }

                if ($panelUnavailable($request)) {
                    return response()->view('errors.panel-unavailable', [
                        'diagnostics' => \App\Support\PanelInfrastructure::diagnostics(true),
                    ], 503);
                }

                return null;
            });
        }
    })->create();

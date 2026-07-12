<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DemoAccountController;
use App\Http\Controllers\Api\DiagnosticReportController;
use App\Http\Controllers\Api\CrashAnalyzerController;
use App\Http\Controllers\Api\CrashHunterController;
use App\Http\Controllers\Api\ServerMonitorMetricsController;
use App\Http\Middleware\AuthenticateAgentToken;
use App\Http\Middleware\AuthenticateSiteApi;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'app' => config('obiora.name'),
        'version' => config('obiora.version'),
    ]));

    Route::middleware(AuthenticateSiteApi::class)->group(function () {
        Route::get('/site-api/ping', fn () => response()->json(['ok' => true]));
        Route::post('/demo-accounts', [DemoAccountController::class, 'store']);
        Route::delete('/demo-accounts/{userId}', [DemoAccountController::class, 'destroy']);
    });

    Route::middleware(AuthenticateAgentToken::class)->group(function () {
        Route::post('/servers/{server}/diagnostics/reports', [DiagnosticReportController::class, 'store']);
        Route::post('/servers/{server}/diagnostics/heartbeat', [DiagnosticReportController::class, 'heartbeat']);
        Route::post('/servers/{server}/crash-analyzer/metrics', [CrashAnalyzerController::class, 'storeMetrics']);
        Route::post('/servers/{server}/crash-analyzer/reports', [CrashAnalyzerController::class, 'storeReport']);
        Route::post('/servers/{server}/monitor/metrics', [ServerMonitorMetricsController::class, 'store']);
        Route::post('/servers/{server}/crash-hunter/metrics', [CrashHunterController::class, 'storeMetrics']);
        Route::post('/servers/{server}/crash-hunter/snapshots', [CrashHunterController::class, 'storeSnapshots']);
        Route::post('/servers/{server}/crash-hunter/witness', [CrashHunterController::class, 'storeWitness']);
        Route::post('/servers/{server}/crash-hunter/incidents', [CrashHunterController::class, 'storeIncident']);
        Route::post('/servers/{server}/crash-hunter/reports', [CrashHunterController::class, 'storeReport']);
        Route::post('/servers/{server}/crash-hunter/events', [CrashHunterController::class, 'storeEvents']);
    });
});

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DiagnosticReportController;
use App\Http\Middleware\AuthenticateAgentToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'app' => config('obiora.name'),
        'version' => config('obiora.version'),
    ]));

    Route::middleware(AuthenticateAgentToken::class)->group(function () {
        Route::post('/servers/{server}/diagnostics/reports', [DiagnosticReportController::class, 'store']);
        Route::post('/servers/{server}/diagnostics/heartbeat', [DiagnosticReportController::class, 'heartbeat']);
    });
});

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/servers/{server}/diagnostics/latest', [DiagnosticReportController::class, 'latest']);
    Route::get('/servers/{server}/diagnostics', [DiagnosticReportController::class, 'index']);
});

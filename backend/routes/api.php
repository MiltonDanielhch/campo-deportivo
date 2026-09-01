<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\TipoCampoController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Satélite de Canchas (GAD Beni)
|--------------------------------------------------------------------------
*/

// ─── Endpoints públicos ─────────────────────────────────────────────────
Route::get('/v1/health', [HealthController::class, 'index']);
Route::post('/v1/auth/login', [AuthController::class, 'login']);

// ─── Endpoints protegidos ───────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/v1/auth/me', [AuthController::class, 'me']);
    Route::post('/v1/auth/logout', [AuthController::class, 'logout']);

    // Tipos de campo — solo admin_parametricas
    Route::middleware('role:admin_parametricas')->prefix('v1/tipos-campo')->group(function () {
        Route::get('/', [TipoCampoController::class, 'index']);
        Route::get('/activos', [TipoCampoController::class, 'activos']);
        Route::post('/', [TipoCampoController::class, 'store']);
        Route::get('/{tipoCampo}', [TipoCampoController::class, 'show']);
        Route::put('/{tipoCampo}', [TipoCampoController::class, 'update']);
        Route::patch('/{tipoCampo}/inhabilitar', [TipoCampoController::class, 'inhabilitar']);
        Route::patch('/{tipoCampo}/reactivar', [TipoCampoController::class, 'reactivar']);
    });
});

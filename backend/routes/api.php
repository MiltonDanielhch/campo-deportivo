<?php

use App\Http\Controllers\Api\V1\CampoDeportivoController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\TarifaCampoController;
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

    // Rutas de admin_parametricas
    Route::middleware('role:admin_parametricas')->group(function () {
        // Tipos de campo
        Route::prefix('v1/tipos-campo')->group(function () {
            Route::get('/', [TipoCampoController::class, 'index']);
            Route::get('/activos', [TipoCampoController::class, 'activos']);
            Route::post('/', [TipoCampoController::class, 'store']);
            Route::get('/{tipoCampo}', [TipoCampoController::class, 'show']);
            Route::put('/{tipoCampo}', [TipoCampoController::class, 'update']);
            Route::patch('/{tipoCampo}/inhabilitar', [TipoCampoController::class, 'inhabilitar']);
            Route::patch('/{tipoCampo}/reactivar', [TipoCampoController::class, 'reactivar']);
        });

        // Campos deportivos (con tarifas anidadas)
        Route::prefix('v1/campos-deportivos')->group(function () {
            Route::get('/', [CampoDeportivoController::class, 'index']);
            Route::post('/', [CampoDeportivoController::class, 'store']);
            Route::get('/{campoDeportivo}', [CampoDeportivoController::class, 'show']);
            Route::put('/{campoDeportivo}', [CampoDeportivoController::class, 'update']);
            Route::patch('/{campoDeportivo}/estado', [CampoDeportivoController::class, 'cambiarEstado']);

            // Tarifas del campo (HU-A3)
            Route::post('/{campoDeportivo}/tarifas', [TarifaCampoController::class, 'store']);
            Route::get('/{campoDeportivo}/tarifas', [TarifaCampoController::class, 'historial']);
        });
    });
});

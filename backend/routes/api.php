<?php

use App\Http\Controllers\Api\V1\AsignacionFuncionarioController;
use App\Http\Controllers\Api\V1\CampoDeportivoController;
use App\Http\Controllers\Api\V1\FuncionarioController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Public\CampoController as PublicCampoController;
use App\Http\Controllers\Api\V1\RolController;
use App\Http\Controllers\Api\V1\TarifaCampoController;
use App\Http\Controllers\Api\V1\TipoCampoController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Public\DisponibilidadController;
use App\Http\Controllers\Api\V1\Public\SolicitudReservaController;

/*
|--------------------------------------------------------------------------
| API Routes — Satélite de Canchas (GAD Beni)
|--------------------------------------------------------------------------
*/

// ─── Endpoints públicos ─────────────────────────────────────────────────
Route::get('/v1/health', [HealthController::class, 'index']);
Route::post('/v1/auth/login', [AuthController::class, 'login']);

// Consulta ciudadana (Épica C, HU-C1): sin autenticación
// throttle:60,1 = salvaguarda mínima; el rate-limiting robusto por IP/
// dispositivo llega con la Épica G (HU-G1).
Route::prefix('v1/public')->middleware('throttle:60,1')->group(function () {
    Route::get('/campos', [PublicCampoController::class, 'index']);
    Route::get('/campos/{campo}', [PublicCampoController::class, 'show']);
    Route::get('/campos/{campo}/disponibilidad', [DisponibilidadController::class, 'show']);
    Route::post('/solicitudes-reserva', [SolicitudReservaController::class, 'store']);
});

// ─── Endpoints protegidos ───────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/v1/auth/me', [AuthController::class, 'me']);
    Route::post('/v1/auth/logout', [AuthController::class, 'logout']);

    // Rutas de admin_parametricas
    Route::middleware('role:admin_parametricas')->group(function (){
        // Tipos de campo
        Route::prefix('v1/tipos-campo')->group(function () {
            Route::get('/', [TipoCampoController::class, 'index']);
            Route::get('/activos', [TipoCampoController::class, 'activos']);
            Route::post('/', [TipoCampoController::class, 'store']);
            Route::get('/{tipoCampo}', [TipoCampoController::class,'show']);
            Route::put('/{tipoCampo}', [TipoCampoController::class,'update']);
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

        // Funcionarios (HU-B1)
        Route::prefix('v1/funcionarios')->group(function () {
            Route::get('/', [FuncionarioController::class, 'index']);
            Route::post('/', [FuncionarioController::class, 'store']);
            Route::get('/{funcionario}', [FuncionarioController::class, 'show']);
            Route::put('/{funcionario}', [FuncionarioController::class, 'update']);
            Route::patch('/{funcionario}/estado', [FuncionarioController::class, 'cambiarEstado']);
        });

        // Asignaciones de campos a funcionarios (HU-B3)
        Route::prefix('v1/funcionarios/{funcionario}/campos')->group(function () {
            Route::get('/', [AsignacionFuncionarioController::class, 'porFuncionario']);
            Route::post('/{campoDeportivo}', [AsignacionFuncionarioController::class, 'asignar']);
            Route::delete('/{campoDeportivo}', [AsignacionFuncionarioController::class, 'desasignar']);
        });

        Route::get('/v1/asignaciones', [AsignacionFuncionarioController::class, 'index']);

        // Roles (para selects de alta de funcionarios)
        Route::get('/v1/roles', [RolController::class, 'index']);
    });
});

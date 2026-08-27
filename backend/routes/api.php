<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Satélite de Canchas (GAD Beni)
|--------------------------------------------------------------------------
*/

// Endpoints públicos
Route::get('/v1/health', [HealthController::class, 'index']);
Route::post('/v1/auth/login', [AuthController::class, 'login']);

// Endpoints protegidos
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/v1/auth/me', [AuthController::class, 'me']);
    Route::post('/v1/auth/logout', [AuthController::class, 'logout']);
});

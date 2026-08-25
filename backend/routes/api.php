<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Satélite de Canchas (GAD Beni)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Público: verificación de conectividad (sin autenticación)
    Route::get('/health', HealthController::class);

    // Protegidas con Sanctum (funcionarios del panel web)
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');
});

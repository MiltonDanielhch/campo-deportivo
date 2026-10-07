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
use App\Http\Controllers\Api\V1\WebhookRecaudacionesController;
use App\Http\Controllers\Api\V1\Public\SolicitudEstadoController;
use App\Http\Controllers\Api\V1\Admin\SolicitudReservaController as AdminSolicitudReservaController;
use App\Http\Controllers\Api\V1\Admin\AsistenciaController as AdminAsistenciaController;
use App\Http\Controllers\Api\V1\Admin\CatalogoSirebController;
use App\Http\Controllers\Api\V1\Admin\ReservasExportController as AdminReservasExportController;
use App\Http\Controllers\Api\V1\OcupacionController;
use App\Http\Controllers\Api\V1\ReportesController;
use App\Http\Controllers\Api\V1\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes — Satélite de Canchas (GAD Beni)
|--------------------------------------------------------------------------
*/

// ─── Endpoints públicos ─────────────────────────────────────────────────
Route::get('/v1/health', [HealthController::class, 'index']);
// El login local se eliminó: la autenticación humana pasa por Ibare
// (GET /auth/login-redirect y GET /auth/callback, en routes/web.php).

// Webhook servidor-a-servidor del Core de Recaudaciones (HU-D4).
// Fuera del grupo público (sin throttle: no debe bloquear reintentos
// del Core) y fuera de auth:sanctum (se autentica por firma HMAC).
Route::post('/v1/webhooks/recaudaciones', [WebhookRecaudacionesController::class, 'handle']);

// ─── Proxy público de imágenes (CORS) ───────────────────────────────────
// Los archivos de /storage los sirve el web server sin cabeceras CORS,
// lo que rompe Image.network en Flutter Web. Esta ruta los sirve vía
// Laravel para que el middleware CORS aplique. Uso: /api/v1/public/storage/campos/x.jpg
Route::get('/v1/public/storage/{path}', function (string $path) {
    // Bloquear path traversal (../)
    if (str_contains($path, '..')) {
        abort(400);
    }

    $base = realpath(storage_path('app/public'));
    $fullPath = realpath(storage_path('app/public/' . $path));

    if (! $base || ! $fullPath || ! str_starts_with($fullPath, $base . DIRECTORY_SEPARATOR)) {
        abort(404);
    }

    return response()->file($fullPath);
})->where('path', '.+');

// Consulta ciudadana (Épica C, HU-C1): sin autenticación
// throttle:60,1 = salvaguarda mínima; el rate-limiting robusto por IP/
// dispositivo llega con la Épica G (HU-G1).
Route::prefix('v1/public')->middleware('throttle:60,1')->group(function () {
    Route::get('/campos', [PublicCampoController::class, 'index']);
    Route::get('/campos/{campo}', [PublicCampoController::class, 'show']);
    Route::get('/campos/{campo}/disponibilidad', [DisponibilidadController::class, 'show']);
    Route::post('/solicitudes-reserva', [SolicitudReservaController::class, 'store']);
    Route::get('/solicitudes-reserva/{codigo}/estado', [SolicitudEstadoController::class, 'show']);
    Route::get('/tipos-campo', [\App\Http\Controllers\Api\V1\Public\TipoCampoController::class, 'index']);
});

// ─── Endpoints protegidos ───────────────────────────────────────────────
Route::middleware('auth.oauth')->group(function () {
    // Auth
    Route::get('/v1/auth/me', [AuthController::class, 'me']);
    Route::post('/v1/auth/logout', [AuthController::class, 'logout']);

    // ─── Dashboard principal (resumen del día) ───
    // Accesible para todos los roles autenticados.
    Route::prefix('v1/dashboard')->group(function () {
        Route::get('/resumen', [DashboardController::class, 'resumen']);
    });
    // ─── FIN Dashboard ───

    // Rutas de admin_parametricas (requieren rol específico)
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
            Route::patch('/{campoDeportivo}/hora-noche', [CampoDeportivoController::class, 'actualizarHoraNoche']);

            // ─── Integración SIREB (Fase C.4) ───
            Route::patch('/{campoDeportivo}/vinculo-sireb', [CatalogoSirebController::class, 'vincular']);
            Route::delete('/{campoDeportivo}/vinculo-sireb', [CatalogoSirebController::class, 'desvincular']);
            // ─── FIN Integración SIREB ───

            // Tarifas del campo (HU-A3) — SOLO LECTURA.
            // El precio lo define SIREB (fuente de verdad del contrato de
            // recaudaciones) y lo espeja `sireb:sincronizar-tarifas`. No existe
            // endpoint para fijar precios a mano.
            Route::get('/{campoDeportivo}/tarifas', [TarifaCampoController::class, 'historial']);
        });

        // ─── Catálogo SIREB (Fase C.4) ───
        Route::prefix('v1/admin/catalogo-sireb')->group(function () {
            Route::get('/campos', [CatalogoSirebController::class, 'index']);
        });

        // ─── Sincronización SIREB (Fase C.4) ───
        Route::prefix('v1/admin/sireb')->group(function () {
            Route::post('/sincronizar-tarifas', [CatalogoSirebController::class, 'sincronizarTarifas']);
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

    // ─── Fase 6.1/6.2: Ocupación en sitio, verificación y mapa global ───
    Route::prefix('v1/ocupacion')->group(function () {
        // Lectura por funcionario: solo sus campos asignados
        Route::middleware('role:admin_parametricas|admin_reservas|funcionario_control')
            ->group(function () {
                Route::get('/mis-campos', [OcupacionController::class, 'misCampos']);
                Route::get('/verificar/{codigo}', [OcupacionController::class, 'verificar']);
            });

        // Mapa global: solo admin_parametricas y gerencia (sin filtro por asignación)
        Route::middleware('role:admin_parametricas|gerencia')
            ->group(function () {
                Route::get('/mapa-global', [OcupacionController::class, 'mapaGlobal']);
            });
    });
    // ─── FIN Fase 6.1/6.2 ───
    // ─── Fase 6.3: Reportes gerenciales ───
    // Solo admin_parametricas y gerencia. NO incluye funcionario_control ni admin_reservas.
    Route::middleware('role:admin_parametricas|gerencia')
        ->prefix('v1/reportes')
        ->group(function () {
            Route::get('/ingresos', [ReportesController::class, 'ingresos']);
            Route::get('/horas-pico', [ReportesController::class, 'horasPico']);
            Route::get('/clientes-frecuentes', [ReportesController::class, 'clientesFrecuentes']);
        });
    // ─── FIN Fase 6.3 ───

    // ─── Fase 7.1: Gestión operativa de solicitudes ───
    Route::prefix('v1/admin/solicitudes-reserva')->group(function () {
        // Lectura: admins + funcionario_control.
        // funcionario_control queda restringido server-side en el service.
        Route::middleware('role:admin_parametricas|admin_reservas|funcionario_control')
            ->group(function () {
                Route::get('/', [AdminSolicitudReservaController::class, 'index']);
                Route::get('/{id}', [AdminSolicitudReservaController::class, 'show']);
            });

        // Acciones sensibles: solo admins.
        Route::middleware('role:admin_parametricas|admin_reservas')
            ->group(function () {
                Route::post('/{id}/anular-liquidacion', [AdminSolicitudReservaController::class, 'anularLiquidacion']);
                Route::get('/{id}/refrescar-sireb', [AdminSolicitudReservaController::class, 'refrescarSireb']);
            });
    });
    // ─── FIN Fase 7.1 ───

        // ─── Fase 7.2: Asistencia en reservas ───
    Route::middleware('role:admin_parametricas|admin_reservas|funcionario_control')
        ->prefix('v1/admin/reservas')
        ->group(function () {
            Route::post('/{id}/asistencia', [AdminAsistenciaController::class, 'store']);
        });
    // ─── FIN Fase 7.2 ───

    // ─── Fase 7.3: Exportación CSV de reservas ───
    // Solo admins. funcionario_control NO puede exportar.
    Route::middleware('role:admin_parametricas|admin_reservas')
        ->prefix('v1/admin/reservas')
        ->group(function () {
            Route::get('/export', [AdminReservasExportController::class, 'csv']);
        });
    // ─── FIN Fase 7.3 ───
});

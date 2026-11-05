<?php

use App\Http\Controllers\Api\V1\Admin\AsistenciaController as AdminAsistenciaController;
use App\Http\Controllers\Api\V1\Admin\CatalogoSirebController;
use App\Http\Controllers\Api\V1\Admin\PiscinaReservaController as AdminPiscinaReservaController;
use App\Http\Controllers\Api\V1\Admin\PiscinaTurnoController;
use App\Http\Controllers\Api\V1\Admin\ReservasExportController as AdminReservasExportController;
use App\Http\Controllers\Api\V1\Admin\SolicitudReservaController as AdminSolicitudReservaController;
use App\Http\Controllers\Api\V1\AsignacionFuncionarioController;
use App\Http\Controllers\Api\V1\CampoDeportivoController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FuncionarioController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OcupacionController;
use App\Http\Controllers\Api\V1\Public\CampoController as PublicCampoController;
use App\Http\Controllers\Api\V1\Public\ConsultaEstadoController;
use App\Http\Controllers\Api\V1\Public\DisponibilidadController;
use App\Http\Controllers\Api\V1\Public\PiscinaController;
use App\Http\Controllers\Api\V1\Public\SolicitudEstadoController;
use App\Http\Controllers\Api\V1\Public\SolicitudReservaController;
use App\Http\Controllers\Api\V1\ReportesController;
use App\Http\Controllers\Api\V1\RolController;
use App\Http\Controllers\Api\V1\TarifaCampoController;
use App\Http\Controllers\Api\V1\TipoCampoController;
use App\Http\Controllers\Api\V1\WebhookRecaudacionesController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Admin\CatalogoArancelarioController;
use App\Http\Controllers\Api\V1\Admin\ArancelVarianteController;
use App\Http\Controllers\Api\V1\UbicacionController;
use App\Http\Controllers\Api\V1\Admin\AsociacionController;

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
// ⟦PENDIENTE A1⟧ el webhook real hoy fails-closed (verificarFirma=false),
// pero la ruta sigue sin throttle → log-flooding posible. Se agrega con A1,
// no acá, para no mezclar frentes (Bloque 2 = variantes).
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
    $fullPath = realpath(storage_path('app/public/'.$path));

    if (! $base || ! $fullPath || ! str_starts_with($fullPath, $base.DIRECTORY_SEPARATOR)) {
        abort(404);
    }

    return response()->file($fullPath);
})->where('path', '.+');

// Consulta ciudadana (Épica C, HU-C1): sin autenticación
// throttle:60,1 = salvaguarda mínima; el rate-limiting robusto por IP/
// dispositivo llega con la Épica G (HU-G1).
Route::prefix('v1/public')->middleware('throttle:60,1')->group(function () {
    Route::get('/campos', [PublicCampoController::class, 'index']);
    Route::get('/ubicaciones', [PublicCampoController::class, 'ubicaciones']);
    Route::get('/campos/{campo}', [PublicCampoController::class, 'show']);
    Route::get('/campos/{campo}/disponibilidad', [DisponibilidadController::class, 'show']);
    Route::post('/solicitudes-reserva', [SolicitudReservaController::class, 'store']);
    Route::get('/solicitudes-reserva/{codigo}/estado', [SolicitudEstadoController::class, 'show']);
    Route::get('solicitudes-reserva', [SolicitudReservaController::class, 'index']);
    // Consulta ciudadana unificada: campos + piscina, por seguimiento, código
    // de ventanilla o CI/NIT/teléfono.
    Route::get('/consultas/{termino}/estado', [ConsultaEstadoController::class, 'show']);
    Route::get('/tipos-campo', [App\Http\Controllers\Api\V1\Public\TipoCampoController::class, 'index']);

    // ─── Módulo Piscina (Fase P7) ───
    Route::get('/piscina/campos/{campoId}/turnos', [PiscinaController::class, 'turnos']);
    Route::post('/piscina/reservas', [PiscinaController::class, 'reservar']);
    Route::get('/piscina/reservas/{codigo}/estado', [PiscinaController::class, 'estado']);
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
        Route::get('/v1/ubicaciones', [UbicacionController::class, 'index']);
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
            // Refresca la caché del catálogo (invalida TTL 10min y re-pagina
            // todo SIREB). Lo usa el botón "Refrescar catálogo" del panel
            // porque el GET de arriba sirve caché y el operador no se entera
            // de los servicios recién dados de alta en SIREB.
            Route::post('/refrescar', [CatalogoSirebController::class, 'refrescar']);
        });

        // ─── Catálogo arancelario clasificado (P8.1, solo lectura) ───
        // Agrupa los servicios SIREB por naturaleza comercial (base / adicional /
        // mensual / hospedaje / piscina / especial) sin tocar reservas. Sirve para
        // validar la clasificación con datos reales antes de modelar variantes (P8.2).
        Route::get('/v1/admin/catalogo-arancelario', [CatalogoArancelarioController::class, 'index']);

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
                Route::get('/piscina-turnos', [OcupacionController::class, 'turnosPiscina']);
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
                Route::post('/', [AdminSolicitudReservaController::class, 'store']);
            });

        // Acciones sensibles: solo admins.
        Route::middleware('role:admin_parametricas|admin_reservas')
            ->group(function () {
                // [B77-B] regularización admin-only. Estática al inicio del grupo
                // sensible, con el alias ADMIN (no el público).
                Route::post('/regularizar', [AdminSolicitudReservaController::class, 'regularizar']);
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

    // ─── Fase P7.7: Módulo Piscina (Admin) ───
    Route::middleware('role:admin_parametricas|admin_reservas')
        ->prefix('v1/admin/piscina')
        ->group(function () {
            // Turnos
            Route::get('/campos/{campoId}/turnos', [PiscinaTurnoController::class, 'index']);
            // [B99-precio] precio SIREB por perfil para una hora (fuente única = resolver).
            Route::get('/campos/{campoId}/tarifas-perfil', [PiscinaTurnoController::class, 'tarifasPerfil']);
            Route::post('/turnos', [PiscinaTurnoController::class, 'store']);
            Route::post('/turnos/lote', [PiscinaTurnoController::class, 'storeLote']);
            Route::patch('/turnos/{turnoId}', [PiscinaTurnoController::class, 'update']);
            Route::delete('/turnos/{turnoId}', [PiscinaTurnoController::class, 'destroy']);

            // Reservas
            Route::get('/reservas', [AdminPiscinaReservaController::class, 'index']);
            Route::post('/reservas', [AdminPiscinaReservaController::class, 'store']);
            // [FIX piscina-export] estática ANTES de {id} y sin el 'piscina/' duplicado.
            // El prefijo del grupo ya es v1/admin/piscina; 'piscina/reservas/...' producía
            // /v1/admin/piscina/piscina/reservas/exportar-csv (ruta muerta) y, al estar
            // después de {id}, el path real del front (/v1/admin/piscina/reservas/
            // exportar-csv) lo capturaba la GET /reservas/{id} con id='exportar-csv'
            // → find() sobre uuid → 22P02 → 500. Mismo patrón que regularizar.
            Route::get('/reservas/exportar-csv', [AdminPiscinaReservaController::class, 'exportarCsv']);
            Route::get('/reservas/{id}', [AdminPiscinaReservaController::class, 'show']);
            Route::post('/reservas/{id}/cancelar', [AdminPiscinaReservaController::class, 'cancelar']);
            Route::get('/reservas/{id}/refrescar-sireb', [AdminPiscinaReservaController::class, 'refrescarSireb']);
        });
    // ─── FIN Fase P7.7 ───

    // ─── Fase P8.2C / P8.3A / Bloque 2: variantes por campo ───
    // Lectura (ventanilla + paramétricas): el modal de ventanilla consume
    // variantes y servicios adicionales para armar la reserva.
    Route::middleware('role:admin_parametricas|admin_reservas')
        ->prefix('v1/admin/campos/{campoDeportivo}')
        ->group(function () {
            Route::get('/variantes', [ArancelVarianteController::class, 'index']);
            Route::get('/servicios-adicionales', [ArancelVarianteController::class, 'serviciosAdicionales']);
        });

    // Escritura (solo paramétricas): crear/editar/desvincular una variante es
    // un vínculo SIREB, equivalente al PATCH /vinculo-sireb de arriba que ya
    // exige admin_parametricas puro. No se abre a admin_reservas para no dar
    // de alta cobros sin pasar por el módulo de paramétricas.
    Route::middleware('role:admin_parametricas')
        ->prefix('v1/admin/campos/{campoDeportivo}')
        ->group(function () {
            Route::post('/variantes', [ArancelVarianteController::class, 'store']);
            Route::put('/variantes/{variante}', [ArancelVarianteController::class, 'update']);
            Route::delete('/variantes/{variante}', [ArancelVarianteController::class, 'destroy']);
        });
    // ─── FIN Fase P8.2C / P8.3A / Bloque 2 ───

    // ─── Asociaciones con convenio (paramétricas) ───
    Route::prefix('v1/asociaciones')->group(function () {
        Route::get('/', [AsociacionController::class, 'index']);
        Route::post('/', [AsociacionController::class, 'store']);
        Route::get('/{asociacion}', [AsociacionController::class, 'show']);
        Route::put('/{asociacion}', [AsociacionController::class, 'update']);
        Route::patch('/{asociacion}/estado', [AsociacionController::class, 'cambiarEstado']);
    });

    // ─── Asociaciones: lectura por CI del responsable (pa' la ventanilla) ───
    Route::middleware('role:admin_parametricas|admin_reservas')
        ->prefix('v1/admin/asociaciones')
        ->group(function () {
            Route::get('/por-responsable/{ci}', [AsociacionController::class, 'porResponsable']);
        });
});

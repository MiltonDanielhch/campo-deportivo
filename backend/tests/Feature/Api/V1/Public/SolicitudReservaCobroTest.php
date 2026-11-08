<?php

namespace Tests\Feature\Api\V1\Public;

use App\Jobs\ReintentarSolicitudJob;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\HorarioAtencion;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use App\Services\AuditoriaService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Integración con SIREB vía cliente simulado (HU-D3, HU-D8 adaptado a SIREB v1).
 *
 * Diseño nuevo: si SIREB falla al crear la liquidación, la solicitud NO se
 * rechaza de inmediato — queda 'pendiente' y se despacha ReintentarSolicitudJob
 * (5 intentos × 60s). La franja sigue ocupada mientras esté pendiente.
 */
class SolicitudReservaCobroTest extends TestCase
{
    use RefreshDatabase;

    private CampoDeportivo $campo;

    protected function setUp(): void
    {
        parent::setUp();

        // Cola fake: los jobs (Expirar/Polling/Reintentar) NO se ejecutan inline.
        // Así la solicitud queda 'pendiente' y los asserts de estado son fiables.
        Queue::fake();

        $rol = Rol::create(['nombre' => 'admin_test', 'permisos' => ['*']]);
        $admin = Funcionario::create([
            'nombre_completo' => 'Admin Test',
            'ci' => 'TEST-' . uniqid(),
            'usuario' => 'admin_' . uniqid(),
            'password_hash' => bcrypt('x'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);

        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);

        $this->campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'TEST-01',
            'nombre' => 'Campo Test',
            'direccion' => 'Dirección',
            'latitud' => -14.84,
            'longitud' => -64.90,
            'estado' => 'activo',
            // UUID del catálogo fake del simulador (SERVICIO_ID)
            'servicio_sireb_id' => 'aaaaaaaa-0000-4000-8000-000000000001',
        ]);

        for ($dia = 1; $dia <= 7; $dia++) {
            HorarioAtencion::create([
                'campo_id' => $this->campo->id,
                'dia_semana' => $dia,
                'hora_apertura' => '08:00',
                'hora_cierre' => '20:00',
            ]);
        }

        $this->campo->update([
            'servicio_sireb_id' => '01a0f3e8-33bc-7130-9614-85a23ea26caf',
            'servicio_sireb_codigo' => 'SEDEDE-CS1',
        ]);

        TarifaCampo::create([
            'campo_id' => $this->campo->id,
            'servicio_sireb_id' => '01a0f3e8-33bc-7130-9614-85a23ea26caf',
            'tarifa_sireb_id' => '01a0f3f8-29e8-71f8-acca-a3c23716c69f',
            'etiqueta_sireb' => 'Diurno',
            'liquidable' => true,
            'tarifario_estado' => 'vigente',
            'estado_tarifa_sireb' => 'activo',
            'rango_desde' => null,
            'rango_hasta' => null,
            'categorias_sireb' => [],
            'perfil' => null,
            'clasificacion' => 'diurna',
            'tipo_tarifa' => 'diurna',
            'precio_por_hora' => 50.00,
            'vigente_desde' => now(),
            'vigente_hasta' => null,
            'sincronizado_en' => now(),
            'creado_por' => null,
        ]);
    }

    private function payload(): array
    {
        return [
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567', // requerido por el flujo SIREB (buscarCliente)
            'franjas' => [[
                'campo_id' => $this->campo->id,
                'fecha' => Carbon::tomorrow()->toDateString(),
                'hora_inicio' => '10:00',
                'hora_fin' => '11:00',
            ]],
        ];
    }

    public function test_exito_puebla_referencia_y_devuelve_cobro(): void
    {
        config(['services.recaudaciones.simulado_modo' => 'exito']);

        $response = $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload());

        $response->assertStatus(201);
        // SIREB v1: referencia = código público (SIM-... en simulado)
        $response->assertJsonPath('cobro.referencia_recaudaciones', fn ($v) => is_string($v) && str_starts_with($v, 'SIM-'));
        // El QR se arma localmente con el código público
        $response->assertJsonPath('cobro.qr_string', fn ($v) => is_string($v) && str_starts_with($v, 'SIREB:'));
        // SIREB v1 no tiene checkout electrónico
        $response->assertJsonPath('cobro.checkout_url', null);

        $solicitud = SolicitudReserva::first();
        $this->assertNotNull($solicitud->referencia_recaudaciones);
        $this->assertNotNull($solicitud->liquidacion_id, 'Debe guardar el id de SIREB para poder anular después');
        $this->assertSame('pendiente', $solicitud->estado->value);

        // Éxito → no se despachó job de reintento
        Queue::assertPushed(ReintentarSolicitudJob::class, 0);
    }

    public function test_fallo_conexion_despacha_reintento_y_queda_pendiente(): void
    {
        config(['services.recaudaciones.simulado_modo' => 'fallo_conexion']);

        // Diseño nuevo: NO se rechaza de inmediato ni se responde 503.
        // La solicitud queda pendiente y el reintento va en background.
        $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload())
            ->assertStatus(201);

        $solicitud = SolicitudReserva::first();
        $this->assertSame('pendiente', $solicitud->estado->value);
        $this->assertNull($solicitud->referencia_recaudaciones, 'Sin liquidación todavía');
        $this->assertNull($solicitud->liquidacion_id);

        Queue::assertPushed(ReintentarSolicitudJob::class, 1);

        // La franja sigue ocupada por la solicitud pendiente (no se libera al acto):
        // una segunda solicitud de la misma franja choca con el EXCLUDE → 409.
        config(['services.recaudaciones.simulado_modo' => 'exito']);
        $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload())
            ->assertStatus(409);

        $this->assertSame(1, SolicitudReserva::count(), 'La segunda solicitud no debe crearse');
    }

    public function test_fallo_conexion_no_revienta_500(): void
    {
        config(['services.recaudaciones.simulado_modo' => 'fallo_conexion']);

        // El espíritu del test original (no 500) se conserva: la caída de SIREB
        // no rompe la request — responde 201 con la solicitud pendiente.
        $response = $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload());

        $response->assertStatus(201);
        $response->assertJsonMissingPath('error');
    }

    /**
     * [25P02-fix] La persistencia de la liquidación (update + auditoría) es
     * atómica: si la auditoría FALLA después de que el HTTP al Core ya creó la
     * liquidación (modo exito), el update se revierte junto con ella y la
     * solicitud queda SIN liquidacion_id — no half-persist. El reintento se
     * despacha igual (canal público), y la idempotency key del Core hace que
     * el re-intento no doble-cobre.
     */
    public function test_liquidacion_no_deja_persistencia_a_medio_si_la_auditoria_falla(): void
    {
        $auditoria = Mockery::mock(AuditoriaService::class);
        $auditoria->shouldReceive('registrar')
            ->andThrow(new \RuntimeException('auditoría fallida a propósito'));
        $this->instance(AuditoriaService::class, $auditoria);

        config(['services.recaudaciones.simulado_modo' => 'exito']);

        $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload())
            ->assertStatus(201);

        $solicitud = SolicitudReserva::first();
        $this->assertSame('pendiente', $solicitud->estado->value);
        $this->assertNull($solicitud->liquidacion_id, 'El update debió revertirse junto con la auditoría (tx atómica)');
        $this->assertNull($solicitud->referencia_recaudaciones, 'Sin referencia local si la persistencia atómica se revirtió');

        Queue::assertPushed(ReintentarSolicitudJob::class, 1);
    }

    /**
     * [25P02-regression] El EXCLUDE (23P01) se captura DESPUÉS del rollback y
     * el re-check corre en conexión limpia; la conexión sigue utilizable. Si
     * el 23P01 hubiera dejado la tx abortada (cascada 25P02), el create
     * posterior revienta 500 en vez de 201. Forzamos el 23P01 por debajo de la
     * capa 1 (inserción directa de un detalle solapado) para ejercitar la rama
     * de concurrencia real, no la de grilla secuencial.
     */
    public function test_23p01_del_exclude_se_recupera_sin_cascada_25p02(): void
    {
        config(['services.recaudaciones.simulado_modo' => 'exito']);
        $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload())
            ->assertStatus(201);

        $campo = CampoDeportivo::first();
        $solicitud = SolicitudReserva::first();

        $caught = null;
        try {
            DB::transaction(function () use ($solicitud, $campo) {
                // Solapa el 10:00-11:00 del primer detalle, mismo campo, misma
                // fecha, estado_solicitud generado = 'pendiente' del padre ->
                // el EXCLUDE rechaza con 23P01 (no 25P02).
                $solicitud->detalles()->create([
                    'campo_id' => $campo->id,
                    'fecha_reserva' => Carbon::tomorrow()->toDateString(),
                    'hora_inicio' => '10:30',
                    'hora_fin' => '11:30',
                    'tarifa_aplicada' => 50.00,
                    'cantidad' => 1,
                ]);
            });
        } catch (QueryException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'El EXCLUDE debió rechazar el solape');
        $this->assertSame('23P01', $caught->getCode(), 'Debe emerger el 23P01 real, no un 25P02 enmascarado');

        // Post-rollback: conexión limpia. Otra franja del mismo campo (14-15,
        // dentro del horario 08-20 y tarifa diurna) se crea con 201. Si hubiera
        // cascada 25P02, esto sería 500.
        $otra = $this->payload();
        $otra['franjas'][0]['hora_inicio'] = '14:00';
        $otra['franjas'][0]['hora_fin'] = '15:00';
        $this->postJson('/api/v1/public/solicitudes-reserva', $otra)
            ->assertStatus(201);
    }
}

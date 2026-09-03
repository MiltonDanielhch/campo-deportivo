<?php

namespace Tests\Feature\Api\V1\Public;

use App\Enums\EstadoSolicitudReserva;
use App\Models\CampoDeportivo;
use App\Models\HorarioAtencion;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaDetalle;
use App\Models\TipoCampo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoint de disponibilidad horaria pública.
 */
class DisponibilidadControllerTest extends TestCase
{
    use RefreshDatabase;

    private TipoCampo $tipoFutbol;
    private CampoDeportivo $campo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tipoFutbol = TipoCampo::create([
            'nombre' => 'Fútbol Test',
            'estado' => 'activo',
        ]);

        $this->campo = CampoDeportivo::create([
            'tipo_campo_id' => $this->tipoFutbol->id,
            'codigo' => 'TEST-001',
            'nombre' => 'Campo Test',
            'direccion' => 'Dirección de prueba',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ]);
    }

    /**
     * Helper: crea una solicitud con un detalle en una franja específica.
     * El trigger replica el estado de la cabecera en el detalle.
     */
    private function crearSolicitudConDetalle(
        Carbon $fecha,
        string $horaInicio,
        string $horaFin,
        EstadoSolicitudReserva $estadoFinal,
    ): SolicitudReserva {
        $ahora = Carbon::now();

        // Crear cabecera con expira_en NOT NULL (+15 min de ventana de pago)
        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'TEST-' . uniqid(),
            'monto_total' => 150.00,
            'nombre_pagador' => 'Juan Test',
            'telefono_pagador' => '12345678',
            'ci_nit_pagador' => '12345678',
            'estado' => EstadoSolicitudReserva::Pendiente,
            'expira_en' => $ahora->copy()->addMinutes(15),
        ]);

        // Crear detalle (estado_solicitud queda en 'pendiente' por defecto)
        SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $this->campo->id,
            'fecha_reserva' => $fecha->toDateString(),
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'tarifa_aplicada' => 150.00,
        ]);

        // Actualizar estado de la cabecera → trigger replica en el detalle
        if ($estadoFinal !== EstadoSolicitudReserva::Pendiente) {
            $solicitud->update(['estado' => $estadoFinal]);
        }

        return $solicitud->fresh();
    }

    public function test_dia_sin_horario_devuelve_grilla_vacia(): void
    {
        // No crear ningún horario para este campo
        $fecha = Carbon::now()->addDay();

        $response = $this->getJson("/api/v1/public/campos/{$this->campo->id}/disponibilidad?fecha={$fecha->toDateString()}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'abierto' => false,
                'bloques' => [],
            ],
        ]);
    }

    public function test_franja_con_solicitud_confirmada_se_etiqueta_ocupada(): void
    {
        // Crear horario para el lunes (dayOfWeekIso = 1)
        $fecha = Carbon::now()->next('Monday');
        HorarioAtencion::create([
            'campo_id' => $this->campo->id,
            'dia_semana' => 1,
            'hora_apertura' => '08:00',
            'hora_cierre' => '20:00',
        ]);

        // Crear solicitud confirmada en la franja 09:00-10:00
        $this->crearSolicitudConDetalle(
            $fecha,
            '09:00:00',
            '10:00:00',
            EstadoSolicitudReserva::Confirmada,
        );

        $response = $this->getJson("/api/v1/public/campos/{$this->campo->id}/disponibilidad?fecha={$fecha->toDateString()}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'abierto' => true,
                'fecha' => $fecha->toDateString(),
            ],
        ]);

        $bloques = $response->json('data.bloques');
        $bloqueOcupado = collect($bloques)->firstWhere('hora_inicio', '09:00:00');

        $this->assertNotNull($bloqueOcupado);
        $this->assertEquals('ocupada', $bloqueOcupado['estado']);
    }

    public function test_franja_con_solicitud_pendiente_se_etiqueta_bloqueada_temporal(): void
    {
        $fecha = Carbon::now()->next('Monday');
        HorarioAtencion::create([
            'campo_id' => $this->campo->id,
            'dia_semana' => 1,
            'hora_apertura' => '08:00',
            'hora_cierre' => '20:00',
        ]);

        // Crear solicitud pendiente en la franja 10:00-11:00
        $this->crearSolicitudConDetalle(
            $fecha,
            '10:00:00',
            '11:00:00',
            EstadoSolicitudReserva::Pendiente,
        );

        $response = $this->getJson("/api/v1/public/campos/{$this->campo->id}/disponibilidad?fecha={$fecha->toDateString()}");

        $response->assertOk();
        $bloques = $response->json('data.bloques');
        $bloqueBloqueado = collect($bloques)->firstWhere('hora_inicio', '10:00:00');

        $this->assertNotNull($bloqueBloqueado);
        $this->assertEquals('bloqueada_temporal', $bloqueBloqueado['estado']);
    }

    public function test_franja_con_estado_fuera_del_conjunto_activo_se_etiqueta_libre(): void
    {
        $fecha = Carbon::now()->next('Monday');
        HorarioAtencion::create([
            'campo_id' => $this->campo->id,
            'dia_semana' => 1,
            'hora_apertura' => '08:00',
            'hora_cierre' => '20:00',
        ]);

        // Crear solicitud expirada en la franja 11:00-12:00
        $this->crearSolicitudConDetalle(
            $fecha,
            '11:00:00',
            '12:00:00',
            EstadoSolicitudReserva::Expirada,
        );

        $response = $this->getJson("/api/v1/public/campos/{$this->campo->id}/disponibilidad?fecha={$fecha->toDateString()}");

        $response->assertOk();
        $bloques = $response->json('data.bloques');
        $bloqueLibre = collect($bloques)->firstWhere('hora_inicio', '11:00:00');

        $this->assertNotNull($bloqueLibre);
        $this->assertEquals('libre', $bloqueLibre['estado']);
    }

    public function test_fecha_pasada_devuelve_422(): void
    {
        $fechaPasada = Carbon::yesterday();

        $response = $this->getJson("/api/v1/public/campos/{$this->campo->id}/disponibilidad?fecha={$fechaPasada->toDateString()}");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('fecha');
    }

    public function test_fecha_mas_de_60_dias_en_futuro_devuelve_422(): void
    {
        $fechaLejana = Carbon::now()->addDays(61);

        $response = $this->getJson("/api/v1/public/campos/{$this->campo->id}/disponibilidad?fecha={$fechaLejana->toDateString()}");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('fecha');
    }
}
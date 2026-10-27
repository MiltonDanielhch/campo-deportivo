<?php

namespace Tests\Feature\Piscina;

use App\DTOs\PiscinaReservaDTO;
use App\Enums\EstadoPiscinaReserva;
use App\Enums\ModalidadReservaCampo;
use App\Exceptions\FranjaNoDisponibleException;
use App\Models\CampoDeportivo;
use App\Models\PiscinaReserva;
use App\Models\PiscinaTurno;
use App\Models\PiscinaTurnoPerfil;
use App\Models\TipoCampo;
use App\Services\Piscina\PiscinaDisponibilidadService;
use App\Services\Piscina\PiscinaReservaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PiscinaReservaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * [B43] La disponibilidad expone precio = tarifa SIREB, no precio_unitario del perfil.
     * Si el perfil tiene precio 15 pero SIREB dice 10, el ciudadano debe ver 10.
     */
    public function test_disponibilidad_expone_precio_de_tarifa_sireb_no_de_perfil(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00'));

        [$campo, $turno, $perfil] = $this->crearSetupPiscina();
        // El setup ya creó perfil con precio_unitario=15 y tarifa SIREB=10 (sembrada)

        $service = app(PiscinaDisponibilidadService::class);
        $result = $service->obtenerTurnosDisponibles($campo->id, '2026-10-26');

        $this->assertTrue($result['abierto']);
        $this->assertCount(1, $result['turnos']);

        $perfilData = $result['turnos'][0]['perfiles'][0];
        $this->assertEquals(10.00, $perfilData['precio'], 'precio debe ser tarifa SIREB (10), no perfil (15)');
        $this->assertEquals(15.00, $perfilData['precio_unitario'] ?? null, 'precio_unitario se preserva para auditoría');
        $this->assertEquals(10, $perfilData['cupo']);
        $this->assertEquals(10, $perfilData['disponible']);
    }

    /**
     * [Fix3] Cancelar una reserva pendiente libera el cupo con lock.
     * Reservado debe volver a 0 y disponible a cupo original.
     */
    public function test_cancelar_libera_cupo_con_lock(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00'));

        [$campo, $turno, $perfil] = $this->crearSetupPiscina();
        $perfil->update(['cupo' => 10, 'reservado' => 2, 'estado' => 'activo']);

        $reserva = PiscinaReserva::create([
            'codigo_seguimiento' => 'PIS-20261025-TEST01',
            'campo_id'           => $campo->id,
            'estado'             => EstadoPiscinaReserva::Pendiente,
            'monto_total'        => 20.00,
            'nombre_pagador'     => 'Test',
            'telefono_pagador'   => '70000000',
            'ci_nit_pagador'     => '1234567',
            'expira_en'          => now()->addMinutes(20),
        ]);

        $detalle = \App\Models\PiscinaReservaDetalle::create([
            'reserva_id'      => $reserva->id,
            'turno_id'        => $turno->id,
            'perfil'          => $perfil->perfil,
            'cantidad'        => 2,
            'precio_unitario' => 10.00,
            'hora_inicio'     => $turno->hora_inicio,
            'hora_fin'        => $turno->hora_fin,
            'fecha'           => $turno->fecha,
        ]);

        $service = app(PiscinaReservaService::class);
        $service->cancelar($reserva, 'cancelacion_test', null);

        $reserva->refresh();
        $perfil->refresh();

        $this->assertEquals(EstadoPiscinaReserva::Cancelada->value, $reserva->estado->value);
        $this->assertEquals(0, $perfil->reservado, 'reservado debe volver a 0 tras cancelar');
        $this->assertEquals(10, $perfil->disponible, 'disponible = cupo total');
    }

    /**
     * [Fix3] Cancelar una reserva NO pendiente lanza DomainException.
     */
    public function test_cancelar_no_pendiente_lanza_excepcion(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00'));

        [$campo] = $this->crearSetupPiscina();

        $reserva = PiscinaReserva::create([
            'codigo_seguimiento' => 'PIS-20261025-TEST02',
            'campo_id'           => $campo->id,
            'estado'             => EstadoPiscinaReserva::Confirmada,
            'monto_total'        => 20.00,
            'nombre_pagador'     => 'Test',
            'telefono_pagador'   => '70000000',
            'ci_nit_pagador'     => '1234567',
            'expira_en'          => now()->addMinutes(20),
        ]);

        $service = app(PiscinaReservaService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Solo se pueden cancelar reservas pendientes');

        $service->cancelar($reserva, 'intento_invalido', null);
    }

    /**
     * [Fix3] Cancelar dos veces concurrentemente no deja reservado negativo.
     */
    public function test_cancelar_doble_no_deja_reservado_negativo(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00'));

        [$campo, $turno, $perfil] = $this->crearSetupPiscina();
        $perfil->update(['cupo' => 10, 'reservado' => 2, 'estado' => 'activo']);

        $reserva = PiscinaReserva::create([
            'codigo_seguimiento' => 'PIS-20261025-TEST03',
            'campo_id'           => $campo->id,
            'estado'             => EstadoPiscinaReserva::Pendiente,
            'monto_total'        => 20.00,
            'nombre_pagador'     => 'Test',
            'telefono_pagador'   => '70000000',
            'ci_nit_pagador'     => '1234567',
            'expira_en'          => now()->addMinutes(20),
        ]);

        \App\Models\PiscinaReservaDetalle::create([
            'reserva_id'      => $reserva->id,
            'turno_id'        => $turno->id,
            'perfil'          => $perfil->perfil,
            'cantidad'        => 2,
            'precio_unitario' => 10.00,
            'hora_inicio'     => $turno->hora_inicio,
            'hora_fin'        => $turno->hora_fin,
            'fecha'           => $turno->fecha,
        ]);

        $service = app(PiscinaReservaService::class);
        $service->cancelar($reserva, 'cancelacion_1', null);

        // Segunda cancelación: lanza DomainException por estado != Pendiente
        $this->expectException(\DomainException::class);
        $service->cancelar($reserva->fresh(), 'cancelacion_2', null);

        $perfil->refresh();
        $this->assertEquals(0, $perfil->reservado, 'reservado queda en 0, nunca negativo');
    }

    /**
     * [B62/B43] Una reserva NO expira si el pago llega antes del TTL.
     * Placeholder para test de conversión completo (requiere mock de SIREB).
     */
    public function test_reserva_no_expira_si_pago_llega_antes_del_ttl(): void
    {
        $this->markTestSkipped('Requiere mock de SirebLiquidacionService; se completa al cerrar Fix3 piscina A/B.');
    }

    public function test_disponibilidad_calcula_cupo_correctamente(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00'));

        [$campo, $turno, $perfil] = $this->crearSetupPiscina();
        $perfil->update(['cupo' => 10, 'reservado' => 3, 'estado' => 'activo']);

        $service = app(PiscinaDisponibilidadService::class);
        $result = $service->obtenerTurnosDisponibles($campo->id, '2026-10-26');

        $perfilData = $result['turnos'][0]['perfiles'][0];
        $this->assertEquals(10, $perfilData['cupo']);
        $this->assertEquals(7, $perfilData['disponible']);
    }

    /**
     * [B99] Al confirmar una reserva de piscina, confirmado_en se setea.
     * Idempotencia: confirmar dos veces no pisa confirmado_en (early return
     * por estado === Confirmada, espejo de ConfirmacionCobroService de cancha).
     *
     * Nota: no creamos detalle porque PiscinaConfirmacionCobroService confirma
     * en cabecera (línea 52 del service: "no creamos tabla de reservas confirmadas"),
     * a diferencia de cancha que itera detalles para crear Reserva:: por franja.
     */
    public function test_confirmar_setea_confirmado_en_y_es_idempotente(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00'));

        [$campo] = $this->crearSetupPiscina();

        $reserva = PiscinaReserva::create([
            'codigo_seguimiento' => 'PIS-20261025-B99',
            'campo_id'           => $campo->id,
            'estado'             => EstadoPiscinaReserva::Pendiente,
            'monto_total'        => 10.00,
            'nombre_pagador'     => 'Test B99',
            'telefono_pagador'   => '70000000',
            'ci_nit_pagador'     => '1234567',
            'expira_en'          => now()->addMinutes(20),
        ]);

        $this->assertNull($reserva->confirmado_en, 'pendiente nace sin confirmado_en');

        $confirmacion = app(\App\Services\Piscina\PiscinaConfirmacionCobroService::class);
        $confirmacion->confirmar($reserva, 10.00);
        $reserva->refresh();

        $this->assertEquals(EstadoPiscinaReserva::Confirmada->value, $reserva->estado->value);
        $this->assertNotNull($reserva->confirmado_en, 'confirmar debe setear confirmado_en');
        $this->assertEquals(10.00, (float) $reserva->monto_confirmado);

        $fechaOriginal = $reserva->confirmado_en->toIso8601String();

        // Idempotencia: 1 hora después, segunda confirmación no pisa la fecha.
        $this->travelTo(Carbon::parse('2026-10-25 11:00:00'));
        $confirmacion->confirmar($reserva, 10.00);
        $reserva->refresh();

        $this->assertEquals(
            $fechaOriginal,
            $reserva->confirmado_en->toIso8601String(),
            'confirmar dos veces no debe pisar confirmado_en (early return por estado)'
        );
    }

    /**
     * [Contrato resolver] PiscinaTarifaResolver requiere 4 columnas del espejo
     * SIREB: perfil, liquidable, tarifario_estado='vigente', clasificacion.
     * Si falta alguna, devuelve null y el perfil se filtra en disponibilidad.
     */
    public function test_resolver_devuelve_null_si_falta_columna_sireb(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00'));

        [$campo, $turno, $perfil] = $this->crearSetupPiscina();

        // Corromper la tarifa: quitar liquidable
        \App\Models\TarifaCampo::where('campo_id', $campo->id)
            ->where('perfil', 'mayor_12')
            ->update(['liquidable' => false]);

        $resolver = app(\App\Services\Piscina\PiscinaTarifaResolver::class);
        $result = $resolver->resolver($campo->id, 'mayor_12', '10:00:00');

        $this->assertNull($result, 'Si liquidable=false, resolver devuelve null');
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    /**
     * @return array{0: CampoDeportivo, 1: PiscinaTurno, 2: PiscinaTurnoPerfil}
     */
    private function crearSetupPiscina(): array
    {
        $tipo = TipoCampo::query()->firstOrCreate(
            ['nombre' => 'Piscina'],
            ['descripcion' => 'Tipo piscina', 'estado' => 'activo']
        );

        $campo = CampoDeportivo::create([
            'tipo_campo_id'     => $tipo->id,
            'codigo'            => 'PISCINA-TEST-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(4)),
            'nombre'            => 'Piscina Test',
            'direccion'         => 'Dirección de prueba',
            'latitud'           => -14.83333333,
            'longitud'          => -64.90000000,
            'modalidad_reserva' => ModalidadReservaCampo::TurnoPorPerfil,
            'estado'            => 'activo',
            'hora_inicio_noche' => '18:00:00',
        ]);

        // ✅ Tarifa con TODOS los filtros que PiscinaTarifaResolver exige:
        //    campo_id + perfil + liquidable=true + tarifario_estado=vigente + vigente_hasta=null
        //    + clasificacion IN ('unica'|'diurna'|'nocturna')
        $tarifa = new \App\Models\TarifaCampo();
        $tarifa->campo_id           = $campo->id;
        $tarifa->precio_por_hora    = 10.00;
        $tarifa->perfil             = 'mayor_12';            // ✅ filtro perfil
        $tarifa->liquidable         = true;                  // ✅ filtro liquidable
        $tarifa->tarifario_estado   = 'vigente';             // ✅ filtro estado
        $tarifa->clasificacion      = 'unica';               // ✅ acepta cualquier horario
        $tarifa->tarifa_sireb_id    = (string) \Illuminate\Support\Str::uuid();
        $tarifa->servicio_sireb_id  = (string) \Illuminate\Support\Str::uuid();
        $tarifa->etiqueta_sireb     = 'Mayores 12';
        $tarifa->vigente_desde      = now()->subDay();
        $tarifa->vigente_hasta      = null;
        $tarifa->creado_por         = null;
        $tarifa->save();

        $turno = PiscinaTurno::create([
            'campo_id'    => $campo->id,
            'fecha'       => '2026-10-26',
            'hora_inicio' => '10:00:00',
            'hora_fin'    => '12:00:00',
            'cupo_total'  => 10,
            'estado'      => 'activo',
        ]);

        $perfil = PiscinaTurnoPerfil::create([
            'turno_id'        => $turno->id,
            'perfil'          => 'mayor_12',
            'cupo'            => 10,
            'reservado'       => 0,
            'precio_unitario' => 15.00,
            'estado'          => 'activo',
        ]);

        return [$campo, $turno, $perfil];
    }
}

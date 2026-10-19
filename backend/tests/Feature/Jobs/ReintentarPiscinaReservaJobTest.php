<?php

namespace Tests\Feature\Jobs;

use App\Enums\EstadoPiscinaReserva;
use App\Exceptions\RecaudacionesApiException;
use App\Jobs\ReintentarPiscinaReservaJob;
use App\Models\CampoDeportivo;
use App\Models\PiscinaReserva;
use App\Models\PiscinaReservaDetalle;
use App\Models\PiscinaTurno;
use App\Models\PiscinaTurnoPerfil;
use App\Models\TipoCampo;
use App\Services\AuditoriaService;
use App\Services\Sireb\SirebLiquidacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ReintentarPiscinaReservaJobTest extends TestCase
{
    use RefreshDatabase;

    private const TARIFA_SIREB_ID = '9f1e2d3c-4b5a-4697-8c3d-1e2f3a4b5c6d';
    private const SERVICIO_SIREB_ID = '8a7b6c5d-4e3f-4a2b-9c1d-0e1f2a3b4c5d';

    private function reservaConCupo(): PiscinaReserva
    {
        $tipo = TipoCampo::create([
            'nombre' => 'Piscina',
            'estado' => 'activo',
        ]);

        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'PIS-J',
            'nombre' => 'Piscina J',
            'direccion' => 'Trinidad',
            'latitud' => -14.84,
            'longitud' => -64.90,
            'estado' => 'activo',
            'modalidad_reserva' => 'turno_por_perfil',
        ]);

        $turno = PiscinaTurno::create([
            'campo_id' => $campo->id,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '08:00:00',
            'hora_fin' => '10:00:00',
            'estado' => 'activo',
        ]);

        PiscinaTurnoPerfil::forceCreate([
            'turno_id' => $turno->id,
            'perfil' => 'mayor_12',
            'cupo' => 10,
            'reservado' => 3,
            'tarifa_sireb_id' => self::TARIFA_SIREB_ID,
            'precio_unitario' => 10.00,
            'estado' => 'activo',
        ]);

        $reserva = PiscinaReserva::create([
            'codigo_seguimiento' => 'PIS-TEST-000001',
            'campo_id' => $campo->id,
            'estado' => EstadoPiscinaReserva::Pendiente,
            'monto_total' => 30.00,
            'nombre_pagador' => 'Vecino',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'ip_cobrador' => '127.0.0.1',
            'expira_en' => now()->addMinutes(20),
        ]);

        PiscinaReservaDetalle::create([
            'reserva_id' => $reserva->id,
            'turno_id' => $turno->id,
            'perfil' => 'mayor_12',
            'cantidad' => 3,
            'precio_unitario' => 10.00,
            'tarifa_sireb_id' => self::TARIFA_SIREB_ID,
            'servicio_sireb_id' => self::SERVICIO_SIREB_ID,
            'etiqueta_sireb' => 'Diurno',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '10:00:00',
            'fecha' => $turno->fecha,
        ]);

        return $reserva;
    }

    /**
     * El job depende del helper, no del client. Mockeamos el helper para
     * simular que la liquidación falla, sin acoplar el test a SIREB.
     */
    private function liquidacionesQueFalla(): SirebLiquidacionService
    {
        $mock = Mockery::mock(SirebLiquidacionService::class);
        $mock->shouldReceive('crearYPersistir')
            ->andThrow(new RecaudacionesApiException('SERVICIO_INACTIVO: el servicio está apagado'));

        return $mock;
    }

    public function test_al_agotar_reintentos_rechaza_y_libera_cupo(): void
    {
        Queue::fake();

        $reserva = $this->reservaConCupo();

        $perfil = PiscinaTurnoPerfil::where('turno_id', $reserva->detalles->first()->turno_id)
            ->where('perfil', 'mayor_12')
            ->first();

        $reservadoAntes = $perfil->reservado;

        $job = new ReintentarPiscinaReservaJob(
            $reserva->id,
            ReintentarPiscinaReservaJob::MAX_REINTENTOS
        );

        $job->handle(
            app(AuditoriaService::class),
            $this->liquidacionesQueFalla()
        );

        $reserva->refresh();
        $perfil->refresh();

        $this->assertSame(EstadoPiscinaReserva::Rechazada, $reserva->estado);
        $this->assertSame('error_cobro_inicial', $reserva->motivo_rechazo);
        $this->assertSame(
            $reservadoAntes - 3,
            $perfil->reservado,
            'El cupo debe liberarse al rechazar la reserva.'
        );

        Queue::assertNothingPushed();
    }

    public function test_con_intento_parcial_redispacha_sin_rechazar(): void
    {
        Queue::fake();

        $reserva = $this->reservaConCupo();

        $job = new ReintentarPiscinaReservaJob($reserva->id, 1);

        $job->handle(
            app(AuditoriaService::class),
            $this->liquidacionesQueFalla()
        );

        $reserva->refresh();

        $this->assertSame(EstadoPiscinaReserva::Pendiente, $reserva->estado);

        Queue::assertPushed(
            ReintentarPiscinaReservaJob::class,
            fn ($pushedJob) => $pushedJob->intento === 2
        );
    }
}
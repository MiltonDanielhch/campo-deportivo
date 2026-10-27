<?php

namespace Tests\Feature\Piscina;

use App\DTOs\PiscinaReservaDTO;
use App\Enums\ModalidadReservaCampo;
use App\Models\CampoDeportivo;
use App\Models\PiscinaReserva;
use App\Models\PiscinaTurno;
use App\Models\PiscinaTurnoPerfil;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use App\Services\Piscina\PiscinaReservaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Services\Sireb\SirebLiquidacionService;

class PiscinaReservaCrearTest extends TestCase
{
    use RefreshDatabase;

protected function setUp(): void
{
    parent::setUp();

    Queue::fake();

    // Mockeamos SIREB pa' probar los gates de PiscinaReservaService::crear()
    // sin depender del catálogo/uuids del simulador.
    $this->mock(SirebLiquidacionService::class, function ($mock) {
        $mock->shouldReceive('crearYPersistir')->andReturnUsing(
            function ($reserva, $items, $idempotencyPrefix, $tablaAuditoria) {
                $reserva->forceFill([
                    'referencia_recaudaciones' => 'SIM-TEST',
                ])->save();
            }
        );
    });
}

    /**
     * Caso 1: camino feliz. Turno futuro -> crea reserva y reserva cupo.
     * Este es el caso que estaba roto por el gate de hora mal interpolado.
     */
    public function test_crea_reserva_piscina_turno_futuro(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00', 'America/La_Paz'));

        [$campo, $turno, $perfil] = $this->crearSetupPiscina(
            '2026-10-26',
            '10:00:00',
            '12:00:00',
        );

        $resultado = app(PiscinaReservaService::class)->crear($this->dto($turno), true);

        $this->assertStringStartsWith('PIS-', $resultado['codigo_seguimiento']);
        $this->assertEquals(10.00, $resultado['monto_total']);
        $this->assertIsString($resultado['referencia_recaudaciones']);
        $this->assertTrue(
            PiscinaReserva::where('codigo_seguimiento', $resultado['codigo_seguimiento'])->exists(),
        );

        $perfil->refresh();
        $this->assertSame(1, (int) $perfil->reservado);
        $this->assertSame(9, (int) $perfil->disponible);
    }

    /**
     * Caso 2: gate de HORA. Turno de HOY ya corrido -> InvalidArgumentException "ya comenzó".
     */
    public function test_rechaza_turno_de_hoy_ya_corrido(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 13:00:00', 'America/La_Paz'));

        [$campo, $turno, $perfil] = $this->crearSetupPiscina(
            '2026-10-26',
            '10:00:00',
            '12:00:00',
        );

        $this->expectarInvalidArgument(function () use ($turno) {
            app(PiscinaReservaService::class)->crear($this->dto($turno), true);
        }, 'ya comenzó');

        $this->assertSame(0, PiscinaReserva::count());

        $perfil->refresh();
        $this->assertSame(0, (int) $perfil->reservado);
    }

    /**
     * Caso 3: gate de FECHA. Turno de AYER -> InvalidArgumentException "turnos pasados".
     */
    public function test_rechaza_turno_de_ayer(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 10:00:00', 'America/La_Paz'));

        [$campo, $turno, $perfil] = $this->crearSetupPiscina(
            '2026-10-25',
            '10:00:00',
            '12:00:00',
        );

        $this->expectarInvalidArgument(function () use ($turno) {
            app(PiscinaReservaService::class)->crear($this->dto($turno), true);
        }, 'No se pueden reservar turnos pasados');

        $this->assertSame(0, PiscinaReserva::count());

        $perfil->refresh();
        $this->assertSame(0, (int) $perfil->reservado);
    }

    /**
     * Caso 4: regresión cross-tz.
     * 2026-10-27 03:30 UTC == 2026-10-26 23:30 America/La_Paz.
     * Un turno de HOY Bolivia a las 23:45 no debe rechazarse como pasado.
     */
    public function test_acepta_turno_de_hoy_cuando_utc_ya_es_manana(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 27, 3, 30, 0, 'UTC'));

        [$campo, $turno, $perfil] = $this->crearSetupPiscina(
            '2026-10-26',
            '23:45:00',
            '23:59:00',
        );

        $resultado = app(PiscinaReservaService::class)->crear($this->dto($turno), true);

        $this->assertStringStartsWith('PIS-', $resultado['codigo_seguimiento']);
        $this->assertTrue(
            PiscinaReserva::where('codigo_seguimiento', $resultado['codigo_seguimiento'])->exists(),
        );
    }

    private function dto(PiscinaTurno $turno): PiscinaReservaDTO
    {
        $fecha = $turno->fecha instanceof \DateTimeInterface
            ? $turno->fecha->format('Y-m-d')
            : (string) $turno->fecha;

        return new PiscinaReservaDTO(
            fecha: $fecha,
            turnoId: $turno->id,
            items: [
                ['perfil' => 'mayor_12', 'cantidad' => 1],
            ],
            nombrePagador: 'Vecino Piscina',
            telefonoPagador: '70000002',
            ciNitPagador: '1234567',
            ipCobrador: '127.0.0.1',
        );
    }

    private function expectarInvalidArgument(callable $fn, string $fragmento): void
    {
        try {
            $fn();
            $this->fail("Debió lanzar InvalidArgumentException con '{$fragmento}'.");
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString($fragmento, $e->getMessage());
        }
    }

    /**
     * Fixture copiado exactamente de PiscinaReservaTest::crearSetupPiscina(),
     * parametrizado solo por fecha/hora del turno.
     *
     * @return array{0: CampoDeportivo, 1: PiscinaTurno, 2: PiscinaTurnoPerfil}
     */
    private function crearSetupPiscina(string $fecha, string $horaInicio, string $horaFin): array
    {
        $tipo = TipoCampo::query()->firstOrCreate(
            ['nombre' => 'Piscina'],
            ['descripcion' => 'Tipo piscina', 'estado' => 'activo'],
        );

        $campo = CampoDeportivo::create([
            'tipo_campo_id'     => $tipo->id,
            'codigo'            => 'PISCINA-TEST-'.Str::upper(Str::random(4)),
            'nombre'            => 'Piscina Test',
            'direccion'         => 'Dirección de prueba',
            'latitud'           => -14.83333333,
            'longitud'          => -64.90000000,
            'modalidad_reserva' => ModalidadReservaCampo::TurnoPorPerfil,
            'estado'            => 'activo',
            'hora_inicio_noche' => '18:00:00',
        ]);

        // ✅ Igual que tu test verde: clasificacion='unica', sin tipo_tarifa,
        // uuids random, liquidable=true, tarifario_estado=vigente, vigente_hasta=null.
        $tarifa = new TarifaCampo();
        $tarifa->campo_id           = $campo->id;
        $tarifa->precio_por_hora    = 10.00;
        $tarifa->perfil             = 'mayor_12';
        $tarifa->liquidable         = true;
        $tarifa->tarifario_estado   = 'vigente';
        $tarifa->clasificacion      = 'unica';
        $tarifa->tarifa_sireb_id    = (string) Str::uuid();
        $tarifa->servicio_sireb_id  = (string) Str::uuid();
        $tarifa->etiqueta_sireb     = 'Mayores 12';
        $tarifa->vigente_desde      = now()->subDay();
        $tarifa->vigente_hasta      = null;
        $tarifa->creado_por         = null;
        $tarifa->save();

        $turno = PiscinaTurno::create([
            'campo_id'    => $campo->id,
            'fecha'       => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin'    => $horaFin,
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

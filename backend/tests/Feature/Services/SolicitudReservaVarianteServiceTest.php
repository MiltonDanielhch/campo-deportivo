<?php

namespace Tests\Feature\Services;

use App\DTOs\FranjaSolicitadaDTO;
use App\DTOs\SolicitudReservaDTO;
use App\Enums\ModalidadReservaCampo;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\HorarioAtencion;
use App\Models\SolicitudReserva;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use App\Services\SolicitudReservaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;
class SolicitudReservaVarianteServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEZONE = 'America/La_Paz';

    private const SVC_DPTO = '01a14203-db11-7390-a168-00a6a71833e2';
    private const SVC_MUNI = '01a141fb-0264-7106-a837-c7ba0c706c82';

    private const TARIFA_DPTO_DIURNA = '01a141f9-70f6-716a-a436-035b7121ee47';
    private const TARIFA_DPTO_NOCTURNA = '01a141f9-a3f0-73a4-8e11-0097d86b5ac0';
    private const TARIFA_MUNI_DIURNA = '01a141fb-78f8-7214-9822-691551a2d5c9';
    private const TARIFA_MUNI_NOCTURNA = '01a141fb-b066-721c-b0a1-6b9800f2ec3c';

    private const CLIENTE_SIREB = '01a141f9-70f6-716a-a436-035b7121ee99';
    private const LIQUIDACION_SIREB = '01a141f9-70f6-716a-a436-035b7121ee88';

    public function test_admin_crea_reserva_con_variante_y_guarda_snapshot(): void
    {
        Queue::fake();

        $campo = $this->crearCampoConVariantes();
        $itemsCapturados = null;
        $this->mockSirebClient($itemsCapturados);

        $variante = ArancelVariante::query()
            ->where('campo_id', $campo->id)
            ->where('codigo', 'DPTO')
            ->firstOrFail();

        $nombre = 'Admin '.$this->randomSuffix();

        $dto = new SolicitudReservaDTO(
            nombrePagador: $nombre,
            telefonoPagador: '70000000',
            ciNitPagador: '1234567',
            franjas: [
                new FranjaSolicitadaDTO(
                    campoId: $campo->id,
                    fecha: $this->manana(),
                    horaInicio: '10:00:00',
                    horaFin: '11:00:00',
                    varianteId: $variante->id,
                ),
            ],
            ipCobrador: '127.0.0.1',
        );

        $resultado = app(SolicitudReservaService::class)->crear($dto, true);
        $solicitud = SolicitudReserva::findOrFail($resultado->solicitud->id);

        $detalle = $solicitud->detalles()->firstOrFail();

        $this->assertSame($variante->id, $detalle->variante_id);
        $this->assertSame(self::SVC_DPTO, $detalle->servicio_sireb_id);
        $this->assertSame(self::TARIFA_DPTO_DIURNA, $detalle->tarifa_sireb_id);
        $this->assertSame('Diurno', $detalle->etiqueta_sireb);
        $this->assertSame(1, (int) $detalle->cantidad);
        $this->assertSame(25.00, (float) $detalle->tarifa_aplicada);

        $this->assertSame([
            ['tarifa_id' => self::TARIFA_DPTO_DIURNA, 'cantidad' => 1],
        ], $itemsCapturados);
    }

    public function test_publico_no_puede_usar_variante_no_visible(): void
    {
        Queue::fake();

        $campo = $this->crearCampoConVariantes();
        $itemsCapturados = null;
        $this->mockSirebClient($itemsCapturados);

        $variante = ArancelVariante::query()
            ->where('campo_id', $campo->id)
            ->where('codigo', 'DPTO')
            ->firstOrFail();

        $dto = new SolicitudReservaDTO(
            nombrePagador: 'Ciudadano '.$this->randomSuffix(),
            telefonoPagador: '70000000',
            ciNitPagador: '1234567',
            franjas: [
                new FranjaSolicitadaDTO(
                    campoId: $campo->id,
                    fecha: $this->manana(),
                    horaInicio: '10:00:00',
                    horaFin: '11:00:00',
                    varianteId: $variante->id,
                ),
            ],
            ipCobrador: '127.0.0.1',
        );

        $this->esperarErrorEnFranjas(
            fn () => app(SolicitudReservaService::class)->crear($dto, false),
            'solo está disponible en ventanilla'
        );

        $this->assertNull($itemsCapturados, 'No se debe llamar a SIREB si la variante no es visible para público.');
    }

    public function test_admin_debe_seleccionar_variante_cuando_el_campo_tiene_variantes_sin_servicio_base(): void
    {
        Queue::fake();

        $campo = $this->crearCampoConVariantes();
        $itemsCapturados = null;
        $this->mockSirebClient($itemsCapturados);

        $dto = new SolicitudReservaDTO(
            nombrePagador: 'Admin '.$this->randomSuffix(),
            telefonoPagador: '70000000',
            ciNitPagador: '1234567',
            franjas: [
                new FranjaSolicitadaDTO(
                    campoId: $campo->id,
                    fecha: $this->manana(),
                    horaInicio: '10:00:00',
                    horaFin: '11:00:00',
                    varianteId: null,
                ),
            ],
            ipCobrador: '127.0.0.1',
        );

        $this->esperarErrorEnFranjas(
            fn () => app(SolicitudReservaService::class)->crear($dto, true),
            'requiere seleccionar una variante arancelaria'
        );

        $this->assertNull($itemsCapturados, 'No se debe llamar a SIREB si falta la variante obligatoria.');
    }

    private function crearCampoConVariantes(): CampoDeportivo
    {
        $tipo = TipoCampo::firstOrCreate(
            ['nombre' => 'Fútbol'],
            ['estado' => 'activo']
        );

        $fecha = Carbon::now(self::TIMEZONE)->addDay();

        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CV-TEST-'.$this->randomSuffix(),
            'nombre' => 'Cancha Vieja Test',
            'direccion' => 'Trinidad',
            'latitud' => -14.84,
            'longitud' => -64.90,
            'estado' => 'activo',
            'modalidad_reserva' => ModalidadReservaCampo::FranjaHoraria,
            'hora_inicio_noche' => '18:00:00',
            'servicio_sireb_id' => null,
            'servicio_sireb_codigo' => null,
        ]);

        HorarioAtencion::create([
            'campo_id' => $campo->id,
            'dia_semana' => $fecha->dayOfWeekIso,
            'hora_apertura' => '08:00',
            'hora_cierre' => '20:00',
        ]);

        $dpto = ArancelVariante::create([
            'campo_id' => $campo->id,
            'codigo' => 'DPTO',
            'nombre' => 'Asociaciones Departamentales',
            'tipo_uso' => 'asociacion',
            'servicio_sireb_id' => self::SVC_DPTO,
            'servicio_sireb_codigo' => 'SEDEDE-CV-DPTO',
            'unidad_medida' => 'hora',
            'visible_publico' => false,
            'activo' => true,
            'orden' => 10,
        ]);

        $muni = ArancelVariante::create([
            'campo_id' => $campo->id,
            'codigo' => 'MUNI',
            'nombre' => 'Asociaciones Municipales',
            'tipo_uso' => 'asociacion',
            'servicio_sireb_id' => self::SVC_MUNI,
            'servicio_sireb_codigo' => 'SEDEDE-CV-MUNI',
            'unidad_medida' => 'hora',
            'visible_publico' => false,
            'activo' => true,
            'orden' => 20,
        ]);

        $this->crearTarifa(
            $campo->id,
            self::SVC_DPTO,
            self::TARIFA_DPTO_DIURNA,
            'Diurno',
            'diurna',
            25.00
        );

        $this->crearTarifa(
            $campo->id,
            self::SVC_DPTO,
            self::TARIFA_DPTO_NOCTURNA,
            'Nocturno',
            'nocturna',
            40.00
        );

        $this->crearTarifa(
            $campo->id,
            self::SVC_MUNI,
            self::TARIFA_MUNI_DIURNA,
            'Diurno',
            'diurna',
            30.00
        );

        $this->crearTarifa(
            $campo->id,
            self::SVC_MUNI,
            self::TARIFA_MUNI_NOCTURNA,
            'Nocturno',
            'nocturna',
            50.00
        );

        return $campo;
    }

    private function crearTarifa(
        string $campoId,
        string $servicioSirebId,
        string $tarifaSirebId,
        string $etiqueta,
        string $clasificacion,
        float $precio
    ): TarifaCampo {
        return TarifaCampo::create([
            'campo_id' => $campoId,
            'servicio_sireb_id' => $servicioSirebId,
            'tarifa_sireb_id' => $tarifaSirebId,
            'etiqueta_sireb' => $etiqueta,
            'liquidable' => true,
            'tarifario_estado' => 'vigente',
            'estado_tarifa_sireb' => 'activo',
            'categorias_sireb' => [],
            'perfil' => null,
            'clasificacion' => $clasificacion,
            'precio_por_hora' => $precio,
            'vigente_desde' => now(),
            'vigente_hasta' => null,
            'creado_por' => null,
        ]);
    }

    private function mockSirebClient(&$itemsCapturados): void
    {
        $client = Mockery::mock(RecaudacionesApiClientInterface::class);

        $client->shouldReceive('buscarCliente')
            ->andReturn(['id' => self::CLIENTE_SIREB]);

        // OJO: sin ->once(). Los tests negativos deben poder validar antes de
        // llamar a SIREB. El test positivo igual falla si itemsCapturados
        // termina siendo null, porque después aserta el array esperado.
        $client->shouldReceive('crearLiquidacion')
            ->andReturnUsing(function (array $items, string $clienteId, string $idempotencyKey, string $referenciaExterna) use (&$itemsCapturados) {
                $itemsCapturados = $items;

                return [
                    'id' => self::LIQUIDACION_SIREB,
                    'codigo_publico' => 'SIM-TEST-'.strtoupper($this->randomSuffix()),
                    'monto' => '25.00',
                    'fecha_vencimiento' => now()->addDays(3)->toDateString(),
                    'items' => $items,
                ];
            });

        $this->app->instance(RecaudacionesApiClientInterface::class, $client);
    }

    private function esperarErrorEnFranjas(callable $fn, string $fragmento): void
    {
        try {
            $fn();
            $this->fail('Se esperaba una ValidationException en franjas.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('franjas', $e->errors());
            $this->assertStringContainsString($fragmento, $e->errors()['franjas'][0]);
        }
    }

    private function manana(): string
    {
        return Carbon::now(self::TIMEZONE)->addDay()->toDateString();
    }

    private function randomSuffix(): string
    {
        return substr(md5(uniqid('', true)), 0, 8);
    }
}

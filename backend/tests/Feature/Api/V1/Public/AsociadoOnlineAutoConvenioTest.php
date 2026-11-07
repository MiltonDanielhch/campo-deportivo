<?php

namespace Tests\Feature\Api\V1\Public;

use App\DTOs\FranjaSolicitadaDTO;
use App\DTOs\SolicitudReservaDTO;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\Asociacion;
use App\Models\AsociacionAlumno;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\Ciudadano;
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

/**
 * Fase 6b: auto-resolución de variante de convenio para asociación logueada
 * que NO manda variante_id. Llamada directa al service (aisla del artefacto
 * HTTP/25P02 y no toca AsociadoOnlineConvenioTest de 6a).
 */
class AsociadoOnlineAutoConvenioTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEZONE = 'America/La_Paz';
    private const SVC_CONVENIO = '01a14203-db11-7390-a168-00a6a71833e2';
    private const TARIFA_DPTO_DIURNA = '01a141f9-70f6-716a-a436-035b7121ee47';
    private const CLIENTE_SIREB = '01a141f9-70f6-716a-a436-035b7121ee99';
    private const LIQUIDACION_SIREB = '01a141f9-70f6-716a-a436-035b7121ee88';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_asociado_departamental_sin_variante_id_auto_resuelve_convenio(): void
    {
        $ci = '7654401';
        $asociacion = $this->crearAsociacionVigente($ci, 'departamental');
        [$campo, $variante] = $this->crearCampoConVarianteTypo('departamental');
        $alumno = $this->crearAlumnoDe($asociacion, '55554001', 'Alumno Auto');

        $itemsCapturados = null;
        $this->mockSirebClient($itemsCapturados);

        $dto = new SolicitudReservaDTO(
            nombrePagador: 'Presidente '.$ci,
            telefonoPagador: '70000000',
            ciNitPagador: $ci,
            franjas: [
                new FranjaSolicitadaDTO(
                    campoId: $campo->id,
                    fecha: $this->manana(),
                    horaInicio: '10:00:00',
                    horaFin: '11:00:00',
                    varianteId: null, // <-- el front NO manda variante
                    alumnosIds: [$alumno->id],
                ),
            ],
            ipCobrador: '127.0.0.1',
        );

        $resultado = app(SolicitudReservaService::class)->crear($dto, false, $asociacion);
        $solicitud = SolicitudReserva::findOrFail($resultado->solicitud->id);

        $detalle = $solicitud->detalles()->firstOrFail();

        // Auto-resolvió la variante DPTO aunque el payload no la pidió.
        $this->assertSame($variante->id, $detalle->variante_id);
        $this->assertSame(25.0, (float) $detalle->tarifa_aplicada);
        $this->assertSame(25.0, (float) $solicitud->monto_total);

        // Liquidación con la tarifa de convenio diurna (fuente del QR).
        $this->assertSame([
            ['tarifa_id' => self::TARIFA_DPTO_DIURNA, 'cantidad' => 1],
        ], $itemsCapturados);

        // Puente alumno<->franja creada (alumnos_ids propagados).
        $this->assertDatabaseHas('solicitud_detalle_alumno', [
            'detalle_id' => $detalle->id,
            'alumno_id' => $alumno->id,
        ]);
    }

    public function test_asociado_municipal_en_campo_solo_departamental_no_auto_resuelve_y_da_422(): void
    {
        $ci = '7654402';
        // Asociación MUNICIPAL, campo con única variante DEPARTAMENTAL.
        $asociacion = $this->crearAsociacionVigente($ci, 'municipal');
        [$campo] = $this->crearCampoConVarianteTypo('departamental');

        $dto = new SolicitudReservaDTO(
            nombrePagador: 'Presidente '.$ci,
            telefonoPagador: '70000000',
            ciNitPagador: $ci,
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

        // autoResolverVarianteConvenio: 0 coincidencias (municipal vs departamental)
        // -> null -> cae al else original -> "requiere seleccionar variante".
        // Falla ruidoso, NUNCA cobra un precio equivocado.
        $this->expectException(ValidationException::class);
        try {
            app(SolicitudReservaService::class)->crear($dto, false, $asociacion);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('franjas', $e->errors());
            $this->assertStringContainsString('requiere seleccionar una variante', $e->errors()['franjas'][0]);
            throw $e;
        }
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function crearAsociacionVigente(string $ci, string $tipo): Asociacion
    {
        return Asociacion::create([
            'nit' => 'NIT-'.$ci,
            'nombre' => 'Asoc '.$ci,
            'tipo' => $tipo,
            'convenio_numero' => 'CONV-'.$ci,
            'convenio_desde' => now()->subMonth()->toDateString(),
            'convenio_hasta' => now()->addYear()->toDateString(),
            'responsable_nombre' => 'Resp '.$ci,
            'responsable_ci' => $ci,
            'responsable_telefono' => '70000000',
            'activo' => true,
        ]);
    }

    private function crearAlumnoDe(Asociacion $asociacion, string $ci, string $nombre): AsociacionAlumno
    {
        $a = new AsociacionAlumno();
        $a->asociacion_id = $asociacion->id;
        $a->ci = $ci;
        $a->nombre_completo = $nombre;
        $a->save();

        return $a;
    }

    /** @return array{0: CampoDeportivo, 1: ArancelVariante} */
    private function crearCampoConVarianteTypo(string $tipoAsociacion): array
    {
        $tipo = TipoCampo::firstOrCreate(['nombre' => 'Fútbol'], ['estado' => 'activo']);
        $fecha = Carbon::now(self::TIMEZONE)->addDay();

        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CV-AUTO-'.uniqid(),
            'nombre' => 'Cancha Auto Convenio',
            'direccion' => 'Trinidad',
            'latitud' => -14.84,
            'longitud' => -64.90,
            'estado' => 'activo',
            'modalidad_reserva' => \App\Enums\ModalidadReservaCampo::FranjaHoraria,
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

        $variante = ArancelVariante::create([
            'campo_id' => $campo->id,
            'codigo' => 'DPTO',
            'nombre' => 'Asociaciones Departamentales',
            'tipo_uso' => 'asociacion',
            'servicio_sireb_id' => self::SVC_CONVENIO,
            'servicio_sireb_codigo' => 'SEDEDE-CV-DPTO',
            'unidad_medida' => 'hora',
            'visible_publico' => false,
            'activo' => true,
            'orden' => 10,
        ]);

        // tipo_asociacion NO está en fillable: se setea por propiedad.
        $variante->tipo_asociacion = $tipoAsociacion;
        $variante->save();

        TarifaCampo::create([
            'campo_id' => $campo->id,
            'servicio_sireb_id' => self::SVC_CONVENIO,
            'tarifa_sireb_id' => self::TARIFA_DPTO_DIURNA,
            'etiqueta_sireb' => 'Diurno',
            'liquidable' => true,
            'tarifario_estado' => 'vigente',
            'estado_tarifa_sireb' => 'activo',
            'categorias_sireb' => [],
            'perfil' => null,
            'clasificacion' => 'diurna',
            'precio_por_hora' => 25.00,
            'vigente_desde' => now(),
            'vigente_hasta' => null,
            'creado_por' => null,
        ]);

        return [$campo, $variante];
    }

    private function mockSirebClient(&$itemsCapturados): void
    {
        $client = Mockery::mock(RecaudacionesApiClientInterface::class);
        $client->shouldReceive('buscarCliente')->andReturn(['id' => self::CLIENTE_SIREB]);
        $client->shouldReceive('crearLiquidacion')->andReturnUsing(
            function (array $items, string $clienteId, string $idempotencyKey, string $referenciaExterna) use (&$itemsCapturados) {
                $itemsCapturados = $items;

                return [
                    'id' => self::LIQUIDACION_SIREB,
                    'codigo_publico' => 'SIM-AUTO-'.strtoupper(uniqid()),
                    'monto' => '25.00',
                    'fecha_vencimiento' => now()->addDays(3)->toDateString(),
                    'items' => $items,
                ];
            }
        );
        $this->app->instance(RecaudacionesApiClientInterface::class, $client);
    }

    private function manana(): string
    {
        return Carbon::now(self::TIMEZONE)->addDay()->toDateString();
    }
}

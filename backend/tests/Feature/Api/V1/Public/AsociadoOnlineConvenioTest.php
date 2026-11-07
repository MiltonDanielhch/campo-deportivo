<?php

namespace Tests\Feature\Api\V1\Public;

use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\Asociacion;
use App\Models\AsociacionAlumno;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\Ciudadano;
use App\Models\Funcionario;
use App\Models\HorarioAtencion;
use App\Models\ParametroSistema;
use App\Models\Rol;
use App\DTOs\FranjaSolicitadaDTO;
use App\DTOs\SolicitudReservaDTO;
use App\Models\SolicitudReserva;
use App\Services\SolicitudReservaService;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Fase 6a: convenio online para el asociado LOGUEADO.
 *
 * Regla de oro: los dos tests anónimos que protegen el fraude del Bs 25 por
 * tipeo (SolicitudReservaControllerTest::asociacion vigente en canal publico
 * paga plazo publico no convenio, y SolicitudReservaVarianteServiceTest::publico
 * no puede usar variante no visible) quedan VERDES sin tocar. Estos tests
 * agregan la rama autenticada y prueban que el token —no el payload— es la
 * credencial que abre el convenio, el plazo largo y los alumnos.
 */
class AsociadoOnlineConvenioTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEZONE = 'America/La_Paz';

    private const SVC_CONVENIO = '01a14203-db11-7390-a168-00a6a71833e2';
    private const TARIFA_CONVENIO_DIURNA = '01a141f9-70f6-716a-a436-035b7121ee47';
    private const TARIFA_CONVENIO_NOCTURNA = '01a141f9-a3f0-73a4-8e11-0097d86b5ac0';
    private const CLIENTE_SIREB = '01a141f9-70f6-716a-a436-035b7121ee99';
    private const LIQUIDACION_SIREB = '01a141f9-70f6-716a-a436-035b7121ee88';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /**
     * Llamada DIRECTA al service (mismo patrón que SolicitudReservaVarianteServiceTest,
     * que pasa). Aísla si el 25P02 viene del service o del path HTTP. Si esta
     * llamada pasa y el postJson falla, el bug está en la integración HTTP
     * (middleware/FormRequest/controller); si esta también falla, vemos la
     * excepción real del UPDATE en crearYPersistir.
     */
    public function test_asociado_logueado_paga_convenio_con_plazo_largo_y_genera_liquidacion_qr(): void
    {
        ParametroSistema::updateOrCreate(
            ['clave' => 'convenio_expiracion_dias'],
            ['valor' => '3', 'descripcion' => 'Test convenio online'],
        );

        $ci = '7654321';
        $asociacion = $this->crearAsociacionVigente($ci);
        [$campo, $variante] = $this->crearCampoConVarianteConvenio();
        $alumno = $this->crearAlumnoDe($asociacion, '55551111', 'Alumno A');

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
                    varianteId: $variante->id,
                    alumnosIds: [$alumno->id],
                ),
            ],
            ipCobrador: '127.0.0.1',
        );

        $resultado = app(SolicitudReservaService::class)
            ->crear($dto, false, $asociacion);

        $solicitud = SolicitudReserva::findOrFail($resultado->solicitud->id);

        $this->assertSame($ci, $solicitud->ci_nit_pagador);
        $this->assertSame(25.0, (float) $solicitud->monto_total);
        $this->assertEqualsWithDelta(3 * 24 * 60 * 60, now()->diffInSeconds($solicitud->expira_en), 5);

        $detalle = $solicitud->detalles()->firstOrFail();
        $this->assertSame($variante->id, $detalle->variante_id);
        $this->assertSame(25.0, (float) $detalle->tarifa_aplicada);

        $this->assertSame([
            ['tarifa_id' => self::TARIFA_CONVENIO_DIURNA, 'cantidad' => 1],
        ], $itemsCapturados);

        $this->assertDatabaseHas('solicitud_detalle_alumno', [
            'detalle_id' => $detalle->id,
            'alumno_id' => $alumno->id,
        ]);
    }

    public function test_asociado_logueado_alumno_de_otra_asociacion_devuelve_422(): void
    {
        $ci = '7654322';
        $asociacion = $this->crearAsociacionVigente($ci);
        $ciudadano = $this->crearCiudadanoAsociado($asociacion, $ci);

        $otra = $this->crearAsociacionVigente('7654399');
        $alumnoOtro = $this->crearAlumnoDe($otra, '55559999', 'Otro');

        [$campo, $variante] = $this->crearCampoConVarianteConvenio();

        Sanctum::actingAs($ciudadano);

        $this->postJson('/api/v1/public/solicitudes-reserva', [
            'nombre_pagador' => 'Pres',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => $ci,
            'franjas' => [[
                'campo_id' => $campo->id,
                'fecha' => $this->manana(),
                'hora_inicio' => '10:00',
                'hora_fin' => '11:00',
                'variante_id' => $variante->id,
                'alumnos_ids' => [$alumnoOtro->id],
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('franjas');

        $this->assertDatabaseCount('solicitudes_reserva', 0);
    }

    public function test_asociado_cuya_asociacion_vencio_entre_login_y_reserva_cae_a_plazo_publico(): void
    {
        ParametroSistema::updateOrCreate(
            ['clave' => 'solicitud_reserva_expiracion_minutos'],
            ['valor' => '30', 'descripcion' => 'Test plazo publico'],
        );

        $ci = '7654323';
        // Convenio VENCIDO (hasta ayer). El token dice es_asociacion=true, pero
        // AsociacionVigenteResolver::porCi la excluye -> convenioAsociacion=null.
        $asociacion = Asociacion::create([
            'nit' => 'NIT-VENC-'.$ci,
            'nombre' => 'Asoc Vencida',
            'tipo' => 'departamental',
            'convenio_numero' => 'CONV-VENC',
            'convenio_desde' => now()->subYear()->toDateString(),
            'convenio_hasta' => now()->subDay()->toDateString(),
            'responsable_nombre' => 'Resp Venc',
            'responsable_ci' => $ci,
            'responsable_telefono' => '70000000',
            'activo' => true,
        ]);
        $ciudadano = $this->crearCiudadanoAsociado($asociacion, $ci);

        $campo = $this->crearCampoBase(); // legacy, sin variante convenio

        Sanctum::actingAs($ciudadano);

        $this->postJson('/api/v1/public/solicitudes-reserva', [
            'nombre_pagador' => 'Pres Venc',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '111',
            'franjas' => [[
                'campo_id' => $campo->id,
                'fecha' => $this->manana(),
                'hora_inicio' => '10:00',
                'hora_fin' => '11:00',
            ]],
        ])->assertStatus(201);

        $solicitud = SolicitudReserva::firstOrFail();

        // El CI sigue saliendo del token (identidad confiable pa' anti-hoarding)...
        $this->assertSame($ci, $solicitud->ci_nit_pagador);
        // ...pero NO hay descuento ni plazo largo: la asociación venció.
        $this->assertSame(150.0, (float) $solicitud->monto_total);
        $this->assertEqualsWithDelta(30 * 60, now()->diffInSeconds($solicitud->expira_en), 5);
    }

    public function test_ciudadano_logueado_no_asociacion_paga_full_con_ci_del_token(): void
    {
        ParametroSistema::updateOrCreate(
            ['clave' => 'solicitud_reserva_expiracion_minutos'],
            ['valor' => '30', 'descripcion' => 'Test plazo publico'],
        );

        $ciudadano = new Ciudadano();
        $ciudadano->ci = '7654324';
        $ciudadano->nombre_completo = 'Vecino Suelto';
        $ciudadano->telefono = '70000000';
        $ciudadano->password_hash = bcrypt('secret');
        $ciudadano->es_asociacion = false;
        $ciudadano->asociacion_id = null;
        $ciudadano->save();

        $campo = $this->crearCampoBase();

        Sanctum::actingAs($ciudadano);

        $this->postJson('/api/v1/public/solicitudes-reserva', [
            'nombre_pagador' => 'Vecino',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '111',
            'franjas' => [[
                'campo_id' => $campo->id,
                'fecha' => $this->manana(),
                'hora_inicio' => '10:00',
                'hora_fin' => '11:00',
            ]],
        ])->assertStatus(201);

        $solicitud = SolicitudReserva::firstOrFail();
        $this->assertSame('7654324', $solicitud->ci_nit_pagador); // token, no payload
        $this->assertSame(150.0, (float) $solicitud->monto_total); // full
        $this->assertEqualsWithDelta(30 * 60, now()->diffInSeconds($solicitud->expira_en), 5);
    }

    public function test_anonimo_sin_token_sigue_bloqueado_de_variante_convenio_aunque_tipee_ci_real(): void
    {
        $ci = '7654325';
        $this->crearAsociacionVigente($ci); // existe, pero NO hay token
        [$campo, $variante] = $this->crearCampoConVarianteConvenio();

        // Sin Sanctum::actingAs: anónimo puro. Tipea el CI de una asociación
        // vigente REAL y aun así NO puede usar la variante de convenio: el
        // visibility gate cierra antes de validarVariantesConvenio.
        $this->postJson('/api/v1/public/solicitudes-reserva', [
            'nombre_pagador' => 'Anonimo',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => $ci,
            'franjas' => [[
                'campo_id' => $campo->id,
                'fecha' => $this->manana(),
                'hora_inicio' => '10:00',
                'hora_fin' => '11:00',
                'variante_id' => $variante->id,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('franjas');

        $this->assertDatabaseCount('solicitudes_reserva', 0);
    }

    public function test_token_de_funcionario_no_abre_convenio_en_la_ruta_publica(): void
    {
        $rol = Rol::create(['nombre' => 'admin_parametricas_pub', 'permisos' => ['*']]);
        $func = Funcionario::create([
            'nombre_completo' => 'Func Pub',
            'ci' => '7654326',
            'usuario' => 'func-pub-'.uniqid(),
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);

        [$campo, $variante] = $this->crearCampoConVarianteConvenio();

        // Un token de Funcionario (no Ciudadano) en la ruta pública: el
        // middleware lo descarta por instanceof -> anónimo -> variante bloqueada.
        Sanctum::actingAs($func);

        $this->postJson('/api/v1/public/solicitudes-reserva', [
            'nombre_pagador' => 'Func',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '7654326',
            'franjas' => [[
                'campo_id' => $campo->id,
                'fecha' => $this->manana(),
                'hora_inicio' => '10:00',
                'hora_fin' => '11:00',
                'variante_id' => $variante->id,
            ]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('solicitudes_reserva', 0);
    }

    // ─────────────────────────────────────────────
    // Helpers (copiados de SolicitudReservaVarianteServiceTest y
    // SolicitudReservaControllerTest, ambos verdes)
    // ─────────────────────────────────────────────

    private function crearAsociacionVigente(string $ci): Asociacion
    {
        return Asociacion::create([
            'nit' => 'NIT-'.$ci,
            'nombre' => 'Asoc '.$ci,
            'tipo' => 'departamental',
            'convenio_numero' => 'CONV-'.$ci,
            'convenio_desde' => now()->subMonth()->toDateString(),
            'convenio_hasta' => now()->addYear()->toDateString(),
            'responsable_nombre' => 'Resp '.$ci,
            'responsable_ci' => $ci,
            'responsable_telefono' => '70000000',
            'activo' => true,
        ]);
    }

    private function crearCiudadanoAsociado(Asociacion $asociacion, string $ci): Ciudadano
    {
        $c = new Ciudadano();
        $c->ci = $ci;
        $c->nombre_completo = 'Presidente '.$ci;
        $c->telefono = '70000000';
        $c->password_hash = bcrypt('secret');
        // es_asociacion / asociacion_id fuera de fillable: por propiedad.
        $c->es_asociacion = true;
        $c->asociacion_id = $asociacion->id;
        $c->save();

        return $c;
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
    private function crearCampoConVarianteConvenio(): array
    {
        $tipo = TipoCampo::firstOrCreate(['nombre' => 'Fútbol'], ['estado' => 'activo']);
        $fecha = Carbon::now(self::TIMEZONE)->addDay();

        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CV-ONLINE-'.uniqid(),
            'nombre' => 'Cancha Online Test',
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

        $this->crearTarifa(self::TARIFA_CONVENIO_DIURNA, 'Diurno', 'diurna', 25.00, $campo->id);
        $this->crearTarifa(self::TARIFA_CONVENIO_NOCTURNA, 'Nocturno', 'nocturna', 40.00, $campo->id);

        return [$campo, $variante];
    }

    private function crearTarifa(string $tarifaSirebId, string $etiqueta, string $clasificacion, float $precio, string $campoId): TarifaCampo
    {
        return TarifaCampo::create([
            'campo_id' => $campoId,
            'servicio_sireb_id' => self::SVC_CONVENIO,
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

    /** Campo legacy (sin variantes), tarifa 150 diurna. Espejo de SolicitudReservaControllerTest. */
    private function crearCampoBase(): CampoDeportivo
    {
        $tipo = TipoCampo::firstOrCreate(['nombre' => 'Fútbol'], ['estado' => 'activo']);

        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'BASE-ONLINE-'.uniqid(),
            'nombre' => 'Campo Base Online',
            'direccion' => 'Dirección de prueba',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ]);

        for ($dia = 1; $dia <= 7; $dia++) {
            HorarioAtencion::create([
                'campo_id' => $campo->id,
                'dia_semana' => $dia,
                'hora_apertura' => '08:00',
                'hora_cierre' => '20:00',
            ]);
        }

        TarifaCampo::create([
            'campo_id' => $campo->id,
            'tipo_tarifa' => 'diurna',
            'precio_por_hora' => 150.0,
            'vigente_desde' => now(),
            'creado_por' => null,
        ]);

        return $campo;
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
                    'codigo_publico' => 'SIM-ONLINE-'.strtoupper(uniqid()),
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

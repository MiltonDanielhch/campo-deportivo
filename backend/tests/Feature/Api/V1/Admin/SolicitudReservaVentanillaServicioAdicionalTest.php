<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\ModalidadReservaCampo;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaServicio;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SolicitudReservaVentanillaServicioAdicionalTest extends TestCase
{
    use RefreshDatabase;

    private const SVC_MUNI = '01a141fb-0264-7106-a837-c7ba0c706c82';
    private const SVC_LIMPIEZA = '01a141f8-689a-707e-8b90-050c79331f47';

    private const TARIFA_MUNI_DIURNA = '01a141fb-78f8-7214-9822-691551a2d5c9';
    private const TARIFA_LIMPIEZA = '01a141f9-70f6-716a-a436-035b7121ee48';

    private const CLIENTE_SIREB = '01a141f9-70f6-716a-a436-035b7121ee99';
    private const LIQUIDACION_SIREB = '01a141f9-70f6-716a-a436-035b7121ee88';

    private Funcionario $admin;

    private CampoDeportivo $campo;

    private ArancelVariante $muni;

    private ArancelVariante $limpieza;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.recaudaciones.simulado_modo' => 'exito']);

        $rolAdmin = Rol::create([
            'nombre' => 'admin_reservas',
            'descripcion' => 'x',
            'permisos' => ['*'],
        ]);

        $this->admin = Funcionario::create([
            'nombre_completo' => 'Admin',
            'ci' => '1',
            'usuario' => 'admin_v_servicio',
            'password_hash' => bcrypt('x'),
            'rol_id' => $rolAdmin->id,
            'estado' => 'activo',
        ]);

        $tipo = TipoCampo::create([
            'nombre' => 'Fútbol',
            'estado' => 'activo',
        ]);

        $this->campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CV-SERV-B',
            'nombre' => 'Cancha Vieja Servicios B',
            'direccion' => 'Trinidad',
            'latitud' => -14.84,
            'longitud' => -64.90,
            'estado' => 'activo',
            'modalidad_reserva' => ModalidadReservaCampo::FranjaHoraria,
            'hora_inicio_noche' => '18:00:00',
            'servicio_sireb_id' => null,
            'servicio_sireb_codigo' => null,
        ]);

        $this->campo->horariosAtencion()->create([
            'dia_semana' => now()->addDay()->dayOfWeekIso,
            'hora_apertura' => '08:00:00',
            'hora_cierre' => '22:00:00',
        ]);

        $this->muni = ArancelVariante::create([
            'campo_id' => $this->campo->id,
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

        $this->limpieza = ArancelVariante::create([
            'campo_id' => $this->campo->id,
            'codigo' => 'LIMPIEZA',
            'nombre' => 'Limpieza post-evento',
            'tipo_uso' => 'servicio_adicional',
            'servicio_sireb_id' => self::SVC_LIMPIEZA,
            'servicio_sireb_codigo' => 'SEDEDE-CV-EVENTO-DEP-LIMPIEZA',
            'unidad_medida' => 'servicio',
            'visible_publico' => false,
            'activo' => true,
            'orden' => 30,
        ]);

        TarifaCampo::create([
            'campo_id' => $this->campo->id,
            'servicio_sireb_id' => self::SVC_MUNI,
            'tarifa_sireb_id' => self::TARIFA_MUNI_DIURNA,
            'etiqueta_sireb' => 'Diurno',
            'liquidable' => true,
            'tarifario_estado' => 'vigente',
            'estado_tarifa_sireb' => 'activo',
            'categorias_sireb' => [],
            'perfil' => null,
            'clasificacion' => 'diurna',
            'precio_por_hora' => 30.00,
            'vigente_desde' => now(),
            'vigente_hasta' => null,
            'creado_por' => null,
        ]);

        TarifaCampo::create([
            'campo_id' => $this->campo->id,
            'servicio_sireb_id' => self::SVC_LIMPIEZA,
            'tarifa_sireb_id' => self::TARIFA_LIMPIEZA,
            'etiqueta_sireb' => 'Limpieza',
            'liquidable' => true,
            'tarifario_estado' => 'vigente',
            'estado_tarifa_sireb' => 'activo',
            'categorias_sireb' => [],
            'perfil' => null,
            'clasificacion' => 'unica',
            'precio_por_hora' => 15.00,
            'vigente_desde' => now(),
            'vigente_hasta' => null,
            'creado_por' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_admin_crea_reserva_con_servicio_adicional_y_sireb_recibe_ambos_items(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $itemsCapturados = null;

        $client = Mockery::mock(RecaudacionesApiClientInterface::class);

        $client->shouldReceive('buscarCliente')
            ->once()
            ->andReturn([
                'id' => self::CLIENTE_SIREB,
            ]);

        $client->shouldReceive('crearLiquidacion')
            ->once()
            ->andReturnUsing(function (...$args) use (&$itemsCapturados) {
                $itemsCapturados = $args[0] ?? [];

                return [
                    'id' => self::LIQUIDACION_SIREB,
                    'codigo_publico' => 'SIM-TEST-SERVICIO',
                    'monto' => '60.00',
                    'fecha_vencimiento' => now()->addDays(3)->toDateString(),
                    'items' => $itemsCapturados,
                ];
            });

        $this->app->instance(
            RecaudacionesApiClientInterface::class,
            $client
        );

        $fecha = now()->addDay()->toDateString();

        $response = $this->postJson('/api/v1/admin/solicitudes-reserva', [
            'nombre_pagador' => 'Vecino Servicio',
            'ci_nit_pagador' => '9876543',
            'telefono_pagador' => '70000001',
            'franjas' => [
                [
                    'campo_id' => $this->campo->id,
                    'variante_id' => $this->muni->id,
                    'fecha' => $fecha,
                    'hora_inicio' => '14:00:00',
                    'hora_fin' => '15:00:00',
                ],
            ],
            'servicios' => [
                [
                    'campo_id' => $this->campo->id,
                    'variante_id' => $this->limpieza->id,
                    'cantidad' => 2,
                ],
            ],
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath(
                'data.codigo_seguimiento',
                fn ($v) => is_string($v) && str_starts_with($v, 'RES-')
            )
            ->assertJsonPath(
                'data.referencia_recaudaciones',
                'SIM-TEST-SERVICIO'
            )
            ->assertJsonPath(
                'data.monto_total',
                fn ($v) => (float) $v === 60.00
            );

        $this->assertSame(
            [
                [
                    'tarifa_id' => self::TARIFA_MUNI_DIURNA,
                    'cantidad' => 1,
                ],
                [
                    'tarifa_id' => self::TARIFA_LIMPIEZA,
                    'cantidad' => 2,
                ],
            ],
            $itemsCapturados,
            'SIREB debe recibir la tarifa horaria y la tarifa del servicio adicional.'
        );

        $solicitud = SolicitudReserva::query()
            ->where('nombre_pagador', 'Vecino Servicio')
            ->firstOrFail();

        $detalle = $solicitud->detalles()->firstOrFail();
        $servicio = SolicitudReservaServicio::query()
            ->where('solicitud_reserva_id', $solicitud->id)
            ->firstOrFail();

        $this->assertSame('pendiente', $solicitud->estado->value);

        $this->assertSame($this->muni->id, $detalle->variante_id);
        $this->assertSame(self::SVC_MUNI, $detalle->servicio_sireb_id);
        $this->assertSame(self::TARIFA_MUNI_DIURNA, $detalle->tarifa_sireb_id);
        $this->assertSame(30.00, (float) $detalle->tarifa_aplicada);

        $this->assertSame($this->campo->id, $servicio->campo_id);
        $this->assertSame($this->limpieza->id, $servicio->variante_id);
        $this->assertSame(self::SVC_LIMPIEZA, $servicio->servicio_sireb_id);
        $this->assertSame(self::TARIFA_LIMPIEZA, $servicio->tarifa_sireb_id);
        $this->assertSame('Limpieza', $servicio->etiqueta_sireb);
        $this->assertSame(2, (int) $servicio->cantidad);
        $this->assertSame(15.00, (float) $servicio->precio_unitario);
        $this->assertSame(30.00, (float) $servicio->subtotal);

        $this->assertDatabaseHas('auditoria', [
            'accion' => 'solicitud_creada_en_ventanilla',
        ]);
    }

    public function test_no_se_puede_agregar_como_servicio_una_variante_horaria(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $fecha = now()->addDay()->toDateString();

        $response = $this->postJson('/api/v1/admin/solicitudes-reserva', [
            'nombre_pagador' => 'Vecino Mal Servicio',
            'ci_nit_pagador' => '9876543',
            'telefono_pagador' => '70000001',
            'franjas' => [
                [
                    'campo_id' => $this->campo->id,
                    'variante_id' => $this->muni->id,
                    'fecha' => $fecha,
                    'hora_inicio' => '14:00:00',
                    'hora_fin' => '15:00:00',
                ],
            ],
            'servicios' => [
                [
                    'campo_id' => $this->campo->id,
                    'variante_id' => $this->muni->id,
                    'cantidad' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertStringContainsString(
            'no puede agregarse como servicio adicional porque es una variante horaria',
            (string) $response->json('errors.servicios.0')
        );
    }
}

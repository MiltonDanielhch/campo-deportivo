<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\ModalidadReservaCampo;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SolicitudReservaVentanillaVarianteTest extends TestCase
{
    use RefreshDatabase;

    private const SVC_DPTO = '01a141f8-689a-707e-8b90-050c79331f46';
    private const SVC_MUNI = '01a141fb-0264-7106-a837-c7ba0c706c82';

    private const TARIFA_DPTO_DIURNA = '01a141f9-70f6-716a-a436-035b7121ee47';
    private const TARIFA_MUNI_DIURNA = '01a141fb-78f8-7214-9822-691551a2d5c9';

    private const CLIENTE_SIREB = '01a141f9-70f6-716a-a436-035b7121ee99';
    private const LIQUIDACION_SIREB = '01a141f9-70f6-716a-a436-035b7121ee88';

    private Funcionario $admin;

    private CampoDeportivo $campo;

    private ArancelVariante $dpto;

    private ArancelVariante $muni;

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
            'usuario' => 'admin_v_variante',
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
            'codigo' => 'CV-V',
            'nombre' => 'Cancha Vieja Ventanilla',
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

        $this->dpto = ArancelVariante::create([
            'campo_id' => $this->campo->id,
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

        $this->crearTarifa(
            $this->dpto,
            self::TARIFA_DPTO_DIURNA,
            'Diurno',
            'diurna',
            25.00
        );

        $this->crearTarifa(
            $this->muni,
            self::TARIFA_MUNI_DIURNA,
            'Diurno',
            'diurna',
            30.00
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function crearTarifa(
        ArancelVariante $variante,
        string $tarifaSirebId,
        string $etiqueta,
        string $clasificacion,
        float $precio
    ): void {
        TarifaCampo::create([
            'campo_id' => $variante->campo_id,
            'servicio_sireb_id' => $variante->servicio_sireb_id,
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

    public function test_admin_crea_reserva_con_variante_desde_http_y_guarda_snapshot(): void
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
                    'codigo_publico' => 'SIM-TEST-VARIANTE',
                    'monto' => '30.00',
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
            'nombre_pagador' => 'Vecino Variante',
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
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath(
                'data.codigo_seguimiento',
                fn ($v) => is_string($v) && str_starts_with($v, 'RES-')
            )
            ->assertJsonPath(
                'data.referencia_recaudaciones',
                'SIM-TEST-VARIANTE'
            )
            ->assertJsonPath(
                'data.monto_total',
                fn ($v) => (float) $v === 30.00
            );

        $this->assertSame(
            [
                [
                    'tarifa_id' => self::TARIFA_MUNI_DIURNA,
                    'cantidad' => 1,
                ],
            ],
            $itemsCapturados,
            'SIREB debe recibir la tarifa espejada de la variante MUNI.'
        );

        $solicitud = SolicitudReserva::query()
            ->where('nombre_pagador', 'Vecino Variante')
            ->firstOrFail();

        $detalle = $solicitud->detalles()->firstOrFail();

        $this->assertSame('pendiente', $solicitud->estado->value);
        $this->assertSame($this->muni->id, $detalle->variante_id);
        $this->assertSame(self::SVC_MUNI, $detalle->servicio_sireb_id);
        $this->assertSame(self::TARIFA_MUNI_DIURNA, $detalle->tarifa_sireb_id);
        $this->assertSame('Diurno', $detalle->etiqueta_sireb);
        $this->assertSame(1, (int) $detalle->cantidad);
        $this->assertSame(30.00, (float) $detalle->tarifa_aplicada);

        $this->assertDatabaseHas('auditoria', [
            'accion' => 'solicitud_creada_en_ventanilla',
        ]);
    }

    public function test_admin_debe_seleccionar_variante_cuando_el_campo_tiene_variantes(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $fecha = now()->addDay()->toDateString();

        $response = $this->postJson('/api/v1/admin/solicitudes-reserva', [
            'nombre_pagador' => 'Vecino Sin Variante',
            'ci_nit_pagador' => '9876543',
            'telefono_pagador' => '70000001',
            'franjas' => [
                [
                    'campo_id' => $this->campo->id,
                    'fecha' => $fecha,
                    'hora_inicio' => '14:00:00',
                    'hora_fin' => '15:00:00',
                ],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertStringContainsString(
            'requiere seleccionar una variante arancelaria',
            (string) $response->json('errors.franjas.0')
        );
    }
}

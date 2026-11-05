<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\ModalidadReservaCampo;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\ArancelVariante;
use App\Models\Asociacion;
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

class ConvenioAsociacionTest extends TestCase
{
    use RefreshDatabase;

    private const SVC_DPTO = '01a141f8-689a-707e-8b90-050c79331f46';
    private const TARIFA_DPTO_DIURNA = '01a141f9-70f6-716a-a436-035b7121ee47';
    private const CLIENTE_SIREB = '01a141f9-70f6-716a-a436-035b7121ee99';
    private const LIQUIDACION_SIREB = '01a141f9-70f6-716a-a436-035b7121ee88';

    private Funcionario $admin;
    private CampoDeportivo $campo;
    private ArancelVariante $convenio;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.recaudaciones.simulado_modo' => 'exito']);

        $rol = Rol::create([
            'nombre' => 'admin_reservas',
            'descripcion' => 'x',
            'permisos' => ['*'],
        ]);

        $this->admin = Funcionario::create([
            'nombre_completo' => 'Admin Convenio',
            'ci' => '1',
            'usuario' => 'admin_convenio',
            'password_hash' => bcrypt('x'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);

        $tipo = TipoCampo::create([
            'nombre' => 'Fútbol',
            'estado' => 'activo',
        ]);

        $this->campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CV-CONVENIO-TEST',
            'nombre' => 'Cancha Vieja Convenio Test',
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

        $this->convenio = ArancelVariante::create([
            'campo_id' => $this->campo->id,
            'codigo' => 'CV-DPTO-TEST',
            'nombre' => 'Convenio Departamental Test',
            'tipo_uso' => 'asociacion',
            'servicio_sireb_id' => self::SVC_DPTO,
            'servicio_sireb_codigo' => 'SEDEDE-CV-DPTO',
            'unidad_medida' => 'hora',
            'visible_publico' => false,
            'activo' => true,
            'orden' => 10,
        ]);

        $this->convenio->forceFill([
            'tipo_asociacion' => 'departamental',
        ])->save();

        TarifaCampo::create([
            'campo_id' => $this->campo->id,
            'servicio_sireb_id' => self::SVC_DPTO,
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
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function asociacion(string $ci, string $hasta = '+1 year'): Asociacion
    {
        return Asociacion::create([
            'nit' => 'NIT-'.uniqid(),
            'nombre' => 'Asociación Test',
            'tipo' => 'departamental',
            'convenio_numero' => 'CONV-TEST',
            'convenio_desde' => now()->subMonth()->toDateString(),
            'convenio_hasta' => now()->modify($hasta)->toDateString(),
            'responsable_nombre' => 'Responsable Test',
            'responsable_ci' => $ci,
            'responsable_telefono' => '70000000',
            'activo' => true,
        ]);
    }

    private function payload(string $ci, string $nombre = 'Pagador Test'): array
    {
        return [
            'nombre_pagador' => $nombre,
            'ci_nit_pagador' => $ci,
            'telefono_pagador' => '70000000',
            'franjas' => [
                [
                    'campo_id' => $this->campo->id,
                    'variante_id' => $this->convenio->id,
                    'fecha' => now()->addDay()->toDateString(),
                    'hora_inicio' => '14:00:00',
                    'hora_fin' => '15:00:00',
                ],
            ],
        ];
    }

    public function test_asociacion_vigente_toma_convenio(): void
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
                    'codigo_publico' => 'SIM-TEST-CONVENIO',
                    'monto' => '25.00',
                    'fecha_vencimiento' => now()->addDays(3)->toDateString(),
                    'items' => $itemsCapturados,
                ];
            });

        $this->app->instance(
            RecaudacionesApiClientInterface::class,
            $client
        );

        $this->asociacion('7654321');

        $response = $this->postJson('/api/v1/admin/solicitudes-reserva', $this->payload('7654321', 'Pagador Convenio'));

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.referencia_recaudaciones', 'SIM-TEST-CONVENIO')
            ->assertJsonPath('data.monto_total', fn ($v) => (float) $v === 25.00);

        $this->assertSame(
            [
                [
                    'tarifa_id' => self::TARIFA_DPTO_DIURNA,
                    'cantidad' => 1,
                ],
            ],
            $itemsCapturados
        );

        $solicitud = SolicitudReserva::query()
            ->where('nombre_pagador', 'Pagador Convenio')
            ->firstOrFail();

        $detalle = $solicitud->detalles()->firstOrFail();

        $this->assertSame($this->convenio->id, $detalle->variante_id);
        $this->assertSame(self::SVC_DPTO, $detalle->servicio_sireb_id);
        $this->assertSame(self::TARIFA_DPTO_DIURNA, $detalle->tarifa_sireb_id);
        $this->assertSame(25.00, (float) $detalle->tarifa_aplicada);

        $this->assertGreaterThan(
            60 * 24,
            now()->diffInMinutes($solicitud->expira_en, false)
        );
    }

    public function test_no_asociacion_no_puede_usar_convenio(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $client = Mockery::mock(RecaudacionesApiClientInterface::class);
        $client->shouldNotReceive('buscarCliente');
        $client->shouldNotReceive('crearLiquidacion');

        $this->app->instance(
            RecaudacionesApiClientInterface::class,
            $client
        );

        $response = $this->postJson('/api/v1/admin/solicitudes-reserva', $this->payload('9999999', 'Pagador No Asociacion'));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('franjas');

        $this->assertStringContainsString(
            'convenio',
            (string) $response->json('errors.franjas.0')
        );
    }

    public function test_asociacion_vencida_no_puede_usar_convenio(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $client = Mockery::mock(RecaudacionesApiClientInterface::class);
        $client->shouldNotReceive('buscarCliente');
        $client->shouldNotReceive('crearLiquidacion');

        $this->app->instance(
            RecaudacionesApiClientInterface::class,
            $client
        );

        $this->asociacion('8888888', '-1 day');

        $response = $this->postJson('/api/v1/admin/solicitudes-reserva', $this->payload('8888888', 'Pagador Vencido'));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('franjas');

        $this->assertStringContainsString(
            'convenio',
            (string) $response->json('errors.franjas.0')
        );
    }
}

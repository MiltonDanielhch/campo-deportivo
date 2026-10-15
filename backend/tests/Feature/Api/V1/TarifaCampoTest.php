<?php

namespace Tests\Feature\Api\V1;

use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\Auditoria;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Rol;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Las tarifas de un campo son de SOLO LECTURA en este sistema: el precio lo
 * define SIREB y `sireb:sincronizar-tarifas` lo espeja en tarifas_campo.
 */
class TarifaCampoTest extends TestCase
{
    use RefreshDatabase;

    private const SERVICIO_SIREB_ID = 'aaaaaaaa-0000-4000-8000-000000000001';

    private Funcionario $admin;
    private CampoDeportivo $campo;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Rol::create([
            'nombre' => 'admin_parametricas',
            'descripcion' => 'Admin paramétricas',
            'permisos' => ['*'],
        ]);

        $this->admin = Funcionario::create([
            'nombre_completo' => 'Admin Test',
            'ci' => '12345678',
            'usuario' => 'admin_test',
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);

        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);

        $this->campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CD-TEST',
            'nombre' => 'Cancha Test',
            'direccion' => 'Trinidad',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
            'servicio_sireb_id' => self::SERVICIO_SIREB_ID,
            'servicio_sireb_codigo' => 'SEDEDE-CS1',
        ]);
    }

    // ─── El precio no se fija a mano ─────────────────────────────────────

    public function test_no_existe_endpoint_para_fijar_precios_a_mano(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/campos-deportivos/{$this->campo->id}/tarifas", [
            'tipo_tarifa' => 'diurna',
            'precio_por_hora' => 500.00,
        ]);

        // 405: la ruta de escritura ya no existe, solo el GET de consulta.
        $response->assertStatus(405);
        $this->assertSame(0, TarifaCampo::count());
    }

    public function test_historial_expone_activas_e_historial_de_solo_lectura(): void
    {
        Sanctum::actingAs($this->admin);

        TarifaCampo::create([
            'campo_id' => $this->campo->id,
            'tipo_tarifa' => 'diurna',
            'precio_por_hora' => 50.00,
            'vigente_desde' => now()->subDay(),
            'vigente_hasta' => now()->subHour(),
            'creado_por' => $this->admin->id,
        ]);

        TarifaCampo::create([
            'campo_id' => $this->campo->id,
            'tipo_tarifa' => 'diurna',
            'precio_por_hora' => 60.00,
            'vigente_desde' => now()->subHour(),
            'vigente_hasta' => null,
            'creado_por' => null,
        ]);

        $response = $this->getJson("/api/v1/campos-deportivos/{$this->campo->id}/tarifas");

        $response->assertOk()
            ->assertJsonCount(2, 'data.historial')
            ->assertJsonPath('data.activas.diurna.precio_por_hora', '60.00')
            ->assertJsonPath('data.activas.nocturna', null);

        $this->assertNull($response->json('data.activas.diurna.vigente_hasta'));
    }

    // ─── El espejo de SIREB ──────────────────────────────────────────────

    public function test_sincronizacion_crea_la_tarifa_espejo_desde_sireb(): void
    {
        $this->simularCatalogoSireb(diurna: 50.0, nocturna: 100.0);

        $exitCode = Artisan::call('sireb:sincronizar-tarifas');

        $this->assertSame(0, $exitCode);

        $activas = TarifaCampo::where('campo_id', $this->campo->id)
            ->whereNull('vigente_hasta')
            ->get()
            ->keyBy('tarifa_sireb_id');

        $this->assertCount(2, $activas);

        $diurna = $activas['bbbbbbbb-0000-4000-8000-000000000001'];
        $nocturna = $activas['bbbbbbbb-0000-4000-8000-000000000002'];

        $this->assertEquals(50.0, (float) $diurna->precio_por_hora);
        $this->assertEquals(100.0, (float) $nocturna->precio_por_hora);

        $this->assertSame('diurna', $diurna->clasificacion);
        $this->assertSame('nocturna', $nocturna->clasificacion);

        $this->assertSame('Diurno', $diurna->etiqueta_sireb);
        $this->assertSame('Nocturno', $nocturna->etiqueta_sireb);

        $this->assertTrue($diurna->liquidable);
        $this->assertTrue($nocturna->liquidable);

        $this->assertSame('vigente', $diurna->tarifario_estado);
        $this->assertSame('vigente', $nocturna->tarifario_estado);

        $this->assertSame(self::SERVICIO_SIREB_ID, $diurna->servicio_sireb_id);
        $this->assertSame(self::SERVICIO_SIREB_ID, $nocturna->servicio_sireb_id);

        // Compatibilidad con lectores viejos.
        $this->assertSame('diurna', $diurna->tipo_tarifa);
        $this->assertSame('nocturna', $nocturna->tipo_tarifa);

        // Nada de lo que escribe la sincronización tiene funcionario creador.
        $this->assertNull($diurna->creado_por);
        $this->assertNull($nocturna->creado_por);
    }

    public function test_sincronizacion_versiona_el_cambio_de_precio(): void
    {
        $tarifaSirebId = 'bbbbbbbb-0000-4000-8000-000000000001';

        // Precio vigente que quedó de una carga vieja, pero ahora como espejo SIREB.
        // El sync nuevo versionea por tarifa_sireb_id, no por tipo_tarifa.
        $anterior = TarifaCampo::create([
            'campo_id' => $this->campo->id,
            'servicio_sireb_id' => self::SERVICIO_SIREB_ID,
            'tarifa_sireb_id' => $tarifaSirebId,
            'etiqueta_sireb' => 'Diurno',
            'liquidable' => true,
            'tarifario_estado' => 'vigente',
            'estado_tarifa_sireb' => 'activo',
            'rango_desde' => null,
            'rango_hasta' => null,
            'categorias_sireb' => [],
            'perfil' => null,
            'clasificacion' => 'diurna',
            'tipo_tarifa' => 'diurna',
            'precio_por_hora' => 50.00,
            'vigente_desde' => now()->subDay(),
            'vigente_hasta' => null,
            'sincronizado_en' => now()->subDay(),
            'creado_por' => $this->admin->id,
        ]);

        $this->simularCatalogoSireb(diurna: 75.0, nocturna: 100.0);

        Artisan::call('sireb:sincronizar-tarifas');

        // La versión anterior se cierra, no se pisa: el historial la conserva.
        $this->assertNotNull($anterior->fresh()->vigente_hasta);
        $this->assertEquals(50.0, (float) $anterior->fresh()->precio_por_hora);

        $nueva = TarifaCampo::where('campo_id', $this->campo->id)
            ->where('tarifa_sireb_id', $tarifaSirebId)
            ->whereNull('vigente_hasta')
            ->first();

        $this->assertNotNull($nueva);
        $this->assertNotSame($anterior->id, $nueva->id);
        $this->assertEquals(75.0, (float) $nueva->precio_por_hora);

        $this->assertSame('diurna', $nueva->clasificacion);
        $this->assertSame('Diurno', $nueva->etiqueta_sireb);
        $this->assertTrue($nueva->liquidable);
        $this->assertSame('vigente', $nueva->tarifario_estado);
        $this->assertNull($nueva->creado_por);

        // Queda la traza de auditoría, sin usuario: la hizo el sistema.
        $auditoria = Auditoria::where('tabla', 'tarifas_campo')
            ->where('registro_id', $nueva->id)
            ->where('accion', 'sincronizar_tarifa_sireb')
            ->first();

        $this->assertNotNull($auditoria);
        $this->assertNull($auditoria->usuario_id);
        $this->assertEquals(50.0, (float) $auditoria->datos_anteriores['precio_por_hora']);
        $this->assertEquals(75.0, (float) $auditoria->datos_nuevos['precio_por_hora']);
    }

    public function test_sincronizacion_no_toca_nada_en_dry_run(): void
    {
        $this->simularCatalogoSireb(diurna: 50.0, nocturna: 100.0);

        Artisan::call('sireb:sincronizar-tarifas', ['--dry-run' => true]);

        $this->assertSame(0, TarifaCampo::count());
    }

    // ─── Reglas de la tabla ──────────────────────────────────────────────

    public function test_indice_unico_parcial_impide_dos_activas_de_la_misma_tarifa_sireb(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $datos = [
            'campo_id' => $this->campo->id,
            'servicio_sireb_id' => self::SERVICIO_SIREB_ID,
            'tarifa_sireb_id' => 'cccccccc-0000-4000-8000-000000000001',
            'etiqueta_sireb' => 'Diurno',
            'liquidable' => true,
            'tarifario_estado' => 'vigente',
            'estado_tarifa_sireb' => 'activo',
            'rango_desde' => null,
            'rango_hasta' => null,
            'categorias_sireb' => [],
            'perfil' => null,
            'clasificacion' => 'diurna',
            'tipo_tarifa' => 'diurna',
            'precio_por_hora' => 100.00,
            'vigente_desde' => now(),
            'vigente_hasta' => null,
            'sincronizado_en' => now(),
            'creado_por' => $this->admin->id,
        ];

        TarifaCampo::create($datos);

        // Misma campo_id + misma tarifa_sireb_id + vigente_hasta null → violación.
        TarifaCampo::create(array_merge($datos, [
            'precio_por_hora' => 150.00,
        ]));
    }

    public function test_tarifas_sireb_distintas_si_pueden_estar_activas_a_la_vez(): void
    {
        $base = [
            'campo_id' => $this->campo->id,
            'servicio_sireb_id' => self::SERVICIO_SIREB_ID,
            'liquidable' => true,
            'tarifario_estado' => 'vigente',
            'estado_tarifa_sireb' => 'activo',
            'rango_desde' => null,
            'rango_hasta' => null,
            'categorias_sireb' => [],
            'perfil' => null,
            'vigente_desde' => now(),
            'vigente_hasta' => null,
            'sincronizado_en' => now(),
            'creado_por' => null,
        ];

        TarifaCampo::create(array_merge($base, [
            'tarifa_sireb_id' => 'dddddddd-0000-4000-8000-000000000001',
            'etiqueta_sireb' => 'Diurno',
            'clasificacion' => 'diurna',
            'tipo_tarifa' => 'diurna',
            'precio_por_hora' => 50.00,
        ]));

        TarifaCampo::create(array_merge($base, [
            'tarifa_sireb_id' => 'dddddddd-0000-4000-8000-000000000002',
            'etiqueta_sireb' => 'Nocturno',
            'clasificacion' => 'nocturna',
            'tipo_tarifa' => 'nocturna',
            'precio_por_hora' => 100.00,
        ]));

        $activas = TarifaCampo::where('campo_id', $this->campo->id)
            ->whereNull('vigente_hasta')
            ->count();

        $this->assertSame(2, $activas);
    }

    public function test_cambiar_estado_de_campo_registra_auditoria(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/campos-deportivos/{$this->campo->id}/estado", [
            'estado' => 'mantenimiento',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.estado', 'mantenimiento');

        $auditoria = Auditoria::where('tabla', 'campos_deportivos')
            ->where('registro_id', $this->campo->id)
            ->where('accion', 'cambiar_estado')
            ->first();

        $this->assertNotNull($auditoria);
        $this->assertEquals('activo', $auditoria->datos_anteriores['estado']);
        $this->assertEquals('mantenimiento', $auditoria->datos_nuevos['estado']);
        $this->assertEquals($this->admin->id, $auditoria->usuario_id);
    }

    // ─── Helper ──────────────────────────────────────────────────────────

    /**
     * Reemplaza el cliente de recaudaciones por uno que devuelve el catálogo
     * indicado para el servicio vinculado al campo de prueba.
     *
     * IMPORTANTE: ahora devuelve el shape real de SIREB, incluyendo
     * liquidable, tarifario_estado, rango y categorias, porque el sync nuevo
     * espeja esas señales y el resolvedor las exige.
     */
    private function simularCatalogoSireb(float $diurna, float $nocturna): void
    {
        $servicio = [
            'id' => self::SERVICIO_SIREB_ID,
            'codigo' => 'SEDEDE-CS1',
            'nombre' => 'Cancha Sintética N° 1',
            'descripcion' => 'Alquiler de cancha sintética por hora',
            'unidad_medida' => 'hora',
            'rubro' => 'alquiler_espacio',
            'modo_tarifa' => 'fija',
            'requiere_factura' => false,
            'normativa_referencia' => 'Resolución SEDEDE de aranceles',
            'plazo_pago_dias' => 3,
            'estado' => 'activo',
            'tarifas' => [
                [
                    'id' => 'bbbbbbbb-0000-4000-8000-000000000001',
                    'monto' => number_format($diurna, 2, '.', ''),
                    'etiqueta' => 'Diurno',
                    'rango_desde' => null,
                    'rango_hasta' => null,
                    'sucursal_id' => null,
                    'estado' => 'activo',
                    'tarifario_estado' => 'vigente',
                    'liquidable' => true,
                    'categorias' => [],
                ],
                [
                    'id' => 'bbbbbbbb-0000-4000-8000-000000000002',
                    'monto' => number_format($nocturna, 2, '.', ''),
                    'etiqueta' => 'Nocturno',
                    'rango_desde' => null,
                    'rango_hasta' => null,
                    'sucursal_id' => null,
                    'estado' => 'activo',
                    'tarifario_estado' => 'vigente',
                    'liquidable' => true,
                    'categorias' => [],
                ],
            ],
        ];

        $this->mock(
            RecaudacionesApiClientInterface::class,
            fn ($mock) => $mock->shouldReceive('listarCatalogo')->andReturn([$servicio]),
        );
    }
}

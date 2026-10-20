<?php

namespace Tests\Feature\Console;

use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use Database\Seeders\ArancelVariantePilotoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SincronizarTarifasVariantesTest extends TestCase
{
    use RefreshDatabase;

    // UUIDs reales del catálogo SIREB (SEDEDE-CV-DPTO / SEDEDE-CV-MUNI).
    private const SVC_DPTO = '01a14203-db11-7390-a168-00a6a71833e2';
    private const SVC_MUNI = '01a141fb-0264-7106-a837-c7ba0c706c82';

    private const TARIFA_DPTO_DIURNA = '01a141f9-70f6-716a-a436-035b7121ee47';
    private const TARIFA_DPTO_NOCTURNA = '01a141f9-a3f0-73a4-8e11-0097d86b5ac0';
    private const TARIFA_MUNI_DIURNA = '01a141fb-78f8-7214-9822-691551a2d5c9';
    private const TARIFA_MUNI_NOCTURNA = '01a141fb-b066-721c-b0a1-6b9800f2ec3c';

    public function test_espeja_tarifas_de_variantes_bajo_el_mismo_campo_sin_duplicar(): void
    {
        $this->seed(ArancelVariantePilotoSeeder::class);

        $campo = CampoDeportivo::where('codigo', 'CV-001')->firstOrFail();

        $this->assertSame(2, ArancelVariante::where('campo_id', $campo->id)->count());

        // El campo padre NO debe tener servicio_sireb_id: el vínculo vive en variantes.
        $this->assertNull($campo->servicio_sireb_id);

        $this->app->instance(
            RecaudacionesApiClientInterface::class,
            $this->clientConVariantes()
        );

        $this->artisan('sireb:sincronizar-tarifas')
            ->assertExitCode(0);

        // (a) 4 tarifas activas bajo el MISMO campo, 2 por variante.
        $tarifas = TarifaCampo::where('campo_id', $campo->id)
            ->whereNull('vigente_hasta')
            ->get();

        $this->assertCount(4, $tarifas);

        $this->assertEqualsCanonicalizing(
            [self::SVC_DPTO, self::SVC_MUNI],
            $tarifas->pluck('servicio_sireb_id')->unique()->values()->all()
        );

        $this->assertSame(2, $tarifas->where('servicio_sireb_id', self::SVC_DPTO)->count());
        $this->assertSame(2, $tarifas->where('servicio_sireb_id', self::SVC_MUNI)->count());

        // Cada variante quedó con su servicio_sireb_id resuelto desde SIREB.
        $this->assertSame(
            self::SVC_DPTO,
            ArancelVariante::where('codigo', 'DPTO')->value('servicio_sireb_id')
        );

        $this->assertSame(
            self::SVC_MUNI,
            ArancelVariante::where('codigo', 'MUNI')->value('servicio_sireb_id')
        );

        // (b) No se auto-crearon campos SIR-* duplicados para los servicios de variantes.
        // Este assert valida el parche en asegurarCamposLocales().
        $this->assertDatabaseMissing('campos_deportivos', ['codigo' => 'SIR-SEDEDE-CV-DPTO']);
        $this->assertDatabaseMissing('campos_deportivos', ['codigo' => 'SIR-SEDEDE-CV-MUNI']);
    }

    private function clientConVariantes(): RecaudacionesApiClientInterface
    {
        $mock = Mockery::mock(RecaudacionesApiClientInterface::class);

        $mock->shouldReceive('listarCatalogo')
            ->with(1, 100)
            ->andReturn([
                $this->servicio(
                    self::SVC_DPTO,
                    'SEDEDE-CV-DPTO',
                    [
                        [self::TARIFA_DPTO_DIURNA, '25.00', 'Diurno'],
                        [self::TARIFA_DPTO_NOCTURNA, '40.00', 'Nocturno'],
                    ]
                ),
            ]);

        $mock->shouldReceive('listarCatalogo')
            ->with(2, 100)
            ->andReturn([
                $this->servicio(
                    self::SVC_MUNI,
                    'SEDEDE-CV-MUNI',
                    [
                        [self::TARIFA_MUNI_DIURNA, '30.00', 'Diurno'],
                        [self::TARIFA_MUNI_NOCTURNA, '50.00', 'Nocturno'],
                    ]
                ),
            ]);

        $mock->shouldReceive('listarCatalogo')
            ->with(3, 100)
            ->andReturn([]);

        return $mock;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $tarifas  [id, monto, etiqueta]
     */
    private function servicio(string $id, string $codigo, array $tarifas): array
    {
        return [
            'id' => $id,
            'codigo' => $codigo,
            'nombre' => $codigo,
            'estado' => 'activo',
            'unidad_medida' => 'hora',
            'tarifas' => array_map(
                fn (array $t) => [
                    'id' => $t[0],
                    'monto' => $t[1],
                    'etiqueta' => $t[2],
                    'estado' => 'activo',
                    'tarifario_estado' => 'vigente',
                    'liquidable' => true,
                    'categorias' => [],
                ],
                $tarifas
            ),
        ];
    }
}

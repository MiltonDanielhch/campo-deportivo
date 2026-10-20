<?php

namespace Tests\Feature\Console;

use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use App\Services\CatalogoSirebService;
use Database\Seeders\ArancelVarianteCatalogoCompletoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SincronizarTarifasCatalogoCompletoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function fakeCatalogo(): array
    {
        // Un servicio por código, con UNA tarifa liquidable vigente.
        // UUIDs deterministas por código para poder asertear.
        $codigos = [
            'SEDEDE-PAL-MUNI', 'SEDEDE-PAL-GENERAL', 'SEDEDE-PAL-EVENTO-DEP',
            'SEDEDE-PAL-EVENTO-NO-LIMPIEZA', 'SEDEDE-PAL-EVENTO-NO-ENER-MONO',
            'SEDEDE-PAL-EVENTO-NO-ENER-TRI', 'SEDEDE-PAL-EVENTO-NO-ESCENARIO',
            'SEDEDE-EGM-ENTREN', 'SEDEDE-EGM-ENTREN-CAM', 'SEDEDE-EGM-ENTREN-CAM-DUCHA',
            'SEDEDE-EGM-EVEN-DEP-LIMPIEZA', 'SEDEDE-EGM-EVEN-DEP-ENERGIA',
            'SEDEDE-EGM-EVEN-DEP-ESCENARIO', 'SEDEDE-EGM-EVENTO-NO-LIMPIEZA',
        ];

        return array_map(fn (string $codigo, int $i) => [
            'id' => sprintf('00000000-0000-4000-8000-%012d', $i + 1),
            'codigo' => $codigo,
            'nombre' => $codigo,
            'estado' => 'activo',
            'tarifario' => 'liquidable',
            'unidad_medida' => str_contains($codigo, 'ENERGIA') || str_contains($codigo, 'LIMPIEZA') || str_contains($codigo, 'ESCENARIO') ? 'servicio' : 'hora',
            'tarifas' => [[
                'id' => sprintf('00000000-0001-4000-8000-%012d', $i + 1),
                'etiqueta' => 'Única',
                'monto' => 10 + $i,
                'liquidable' => true,
                'tarifario_estado' => 'vigente',
                'estado' => 'activo',
                'categorias' => [],
                'rango_desde' => null,
                'rango_hasta' => null,
            ]],
        ], $codigos, array_keys($codigos));
    }

    private function mockCatalogo(): void
    {
        $mock = Mockery::mock(CatalogoSirebService::class);
        $mock->shouldReceive('refrescarCatalogo')->andReturn($this->fakeCatalogo());
        $this->instance(CatalogoSirebService::class, $mock);
    }

    public function test_seeder_es_idempotente_y_no_pisa_cv001(): void
    {
        $this->seed(ArancelVarianteCatalogoCompletoSeeder::class);
        $this->seed(ArancelVarianteCatalogoCompletoSeeder::class); // segunda corrida

        $pal = CampoDeportivo::where('codigo', 'PAL-001')->firstOrFail();
        $egm = CampoDeportivo::where('codigo', 'EGM-001')->firstOrFail();

        // 7 variantes por campo, y NO se duplicaron en la segunda corrida.
        $this->assertSame(7, ArancelVariante::where('campo_id', $pal->id)->count());
        $this->assertSame(7, ArancelVariante::where('campo_id', $egm->id)->count());

        // Campo padre sin vínculo directo (el vínculo vive en variantes).
        $this->assertNull($pal->servicio_sireb_id);
        $this->assertNull($egm->servicio_sireb_id);
    }

    public function test_sync_llena_variantes_y_espeja_tarifas_sin_duplicar_campos(): void
    {
        $this->seed(ArancelVarianteCatalogoCompletoSeeder::class);
        $this->mockCatalogo();

        $this->artisan('sireb:sincronizar-tarifas')->assertExitCode(0);

        foreach (['PAL-001', 'EGM-001'] as $codigoCampo) {
            $campo = CampoDeportivo::where('codigo', $codigoCampo)->firstOrFail();

            $variantes = ArancelVariante::where('campo_id', $campo->id)->get();

            // Todas las variantes quedaron vinculadas a SIREB.
            $this->assertSame(7, $variantes->count());
            $this->assertSame(
                7,
                $variantes->whereNotNull('servicio_sireb_id')->count(),
                "Todas las variantes de {$codigoCampo} deben tener servicio_sireb_id."
            );

            // Una tarifa vigente espejada por servicio.
            $this->assertSame(
                7,
                TarifaCampo::where('campo_id', $campo->id)
                    ->whereNull('vigente_hasta')
                    ->count(),
                "Debe haber 7 tarifas vigentes espejadas en {$codigoCampo}."
            );
        }

        // El sync NO debió auto-crear campos SIR-* duplicados para estos códigos.
        $this->assertFalse(
            CampoDeportivo::where('codigo', 'like', 'SIR-%')
                ->whereIn('servicio_sireb_codigo', [
                    'SEDEDE-PAL-MUNI', 'SEDEDE-EGM-ENTREN',
                ])
                ->where('estado', 'activo')
                ->exists(),
            'No debe quedar un campo SIR-* activo duplicando una variante.'
        );
    }
}

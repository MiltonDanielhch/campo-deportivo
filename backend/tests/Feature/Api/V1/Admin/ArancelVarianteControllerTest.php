<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\ArancelVarianteController;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArancelVarianteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_devuelve_variantes_activas_con_tarifas_espejadas(): void
    {
        $tipo = TipoCampo::firstOrCreate(
            ['nombre' => 'Fútbol'],
            ['estado' => 'activo']
        );

        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CV-CTRL-'.substr(md5(uniqid('', true)), 0, 8),
            'nombre' => 'Cancha Vieja Control',
            'direccion' => 'Trinidad',
            'latitud' => -14.84,
            'longitud' => -64.90,
            'estado' => 'activo',
            'modalidad_reserva' => 'franja_horaria',
            'hora_inicio_noche' => '18:00:00',
            'servicio_sireb_id' => null,
            'servicio_sireb_codigo' => null,
        ]);

        $dpto = ArancelVariante::create([
            'campo_id' => $campo->id,
            'codigo' => 'DPTO',
            'nombre' => 'Asociaciones Departamentales',
            'tipo_uso' => 'asociacion',
            'servicio_sireb_id' => '01a14203-db11-7390-a168-00a6a71833e2',
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
            'servicio_sireb_id' => '01a141fb-0264-7106-a837-c7ba0c706c82',
            'servicio_sireb_codigo' => 'SEDEDE-CV-MUNI',
            'unidad_medida' => 'hora',
            'visible_publico' => false,
            'activo' => true,
            'orden' => 20,
        ]);

        ArancelVariante::create([
            'campo_id' => $campo->id,
            'codigo' => 'INACTIVA',
            'nombre' => 'Variante inactiva',
            'tipo_uso' => 'otro',
            'servicio_sireb_id' => '01a141fb-0264-7106-a837-c7ba0c706c99',
            'servicio_sireb_codigo' => 'SEDEDE-CV-INACTIVA',
            'unidad_medida' => 'hora',
            'visible_publico' => false,
            'activo' => false,
            'orden' => 30,
        ]);

        TarifaCampo::create([
            'campo_id' => $campo->id,
            'servicio_sireb_id' => $dpto->servicio_sireb_id,
            'tarifa_sireb_id' => '01a141f9-70f6-716a-a436-035b7121ee47',
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

        TarifaCampo::create([
            'campo_id' => $campo->id,
            'servicio_sireb_id' => $dpto->servicio_sireb_id,
            'tarifa_sireb_id' => '01a141f9-a3f0-73a4-8e11-0097d86b5ac0',
            'etiqueta_sireb' => 'Nocturno',
            'liquidable' => true,
            'tarifario_estado' => 'vigente',
            'estado_tarifa_sireb' => 'activo',
            'categorias_sireb' => [],
            'perfil' => null,
            'clasificacion' => 'nocturna',
            'precio_por_hora' => 40.00,
            'vigente_desde' => now(),
            'vigente_hasta' => null,
            'creado_por' => null,
        ]);

        TarifaCampo::create([
            'campo_id' => $campo->id,
            'servicio_sireb_id' => $muni->servicio_sireb_id,
            'tarifa_sireb_id' => '01a141fb-78f8-7214-9822-691551a2d5c9',
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
            'campo_id' => $campo->id,
            'servicio_sireb_id' => $muni->servicio_sireb_id,
            'tarifa_sireb_id' => '01a141fb-b066-721c-b0a1-6b9800f2ec3c',
            'etiqueta_sireb' => 'Nocturno',
            'liquidable' => true,
            'tarifario_estado' => 'vigente',
            'estado_tarifa_sireb' => 'activo',
            'categorias_sireb' => [],
            'perfil' => null,
            'clasificacion' => 'nocturna',
            'precio_por_hora' => 50.00,
            'vigente_desde' => now(),
            'vigente_hasta' => null,
            'creado_por' => null,
        ]);

        $response = app(ArancelVarianteController::class)->index($campo);

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(2, $payload['data']);

        $this->assertSame('DPTO', $payload['data'][0]['codigo']);
        $this->assertSame('MUNI', $payload['data'][1]['codigo']);

        $this->assertCount(2, $payload['data'][0]['tarifas']);
        $this->assertCount(2, $payload['data'][1]['tarifas']);

        $this->assertSame(25.00, $payload['data'][0]['tarifas'][0]['monto']);
        $this->assertSame(40.00, $payload['data'][0]['tarifas'][1]['monto']);

        $this->assertSame(30.00, $payload['data'][1]['tarifas'][0]['monto']);
        $this->assertSame(50.00, $payload['data'][1]['tarifas'][1]['monto']);

        $this->assertFalse($payload['data'][0]['visible_publico']);
        $this->assertFalse($payload['data'][1]['visible_publico']);
    }
}

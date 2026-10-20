<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\ArancelVarianteController;
use App\Models\ArancelVariante;
use App\Models\CampoDeportivo;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArancelVarianteServicioAdicionalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_servicios_adicionales_devuelve_variantes_no_horarias_con_tarifas(): void
    {
        $tipo = TipoCampo::firstOrCreate(
            ['nombre' => 'Fútbol'],
            ['estado' => 'activo']
        );

        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CV-SERV-'.substr(md5(uniqid('', true)), 0, 8),
            'nombre' => 'Cancha Vieja Servicios',
            'direccion' => 'Trinidad',
            'latitud' => -14.84,
            'longitud' => -64.90,
            'estado' => 'activo',
            'modalidad_reserva' => 'franja_horaria',
            'hora_inicio_noche' => '18:00:00',
            'servicio_sireb_id' => null,
            'servicio_sireb_codigo' => null,
        ]);

        ArancelVariante::create([
            'campo_id' => $campo->id,
            'codigo' => 'DPTO',
            'nombre' => 'Asociaciones Departamentales',
            'tipo_uso' => 'asociacion',
            'servicio_sireb_id' => '01a141f8-689a-707e-8b90-050c79331f46',
            'servicio_sireb_codigo' => 'SEDEDE-CV-DPTO',
            'unidad_medida' => 'hora',
            'visible_publico' => false,
            'activo' => true,
            'orden' => 10,
        ]);

        $limpieza = ArancelVariante::create([
            'campo_id' => $campo->id,
            'codigo' => 'LIMPIEZA',
            'nombre' => 'Limpieza post-evento',
            'tipo_uso' => 'servicio_adicional',
            'servicio_sireb_id' => '01a141f8-689a-707e-8b90-050c79331f47',
            'servicio_sireb_codigo' => 'SEDEDE-CV-EVENTO-DEP-LIMPIEZA',
            'unidad_medida' => 'servicio',
            'visible_publico' => false,
            'activo' => true,
            'orden' => 30,
        ]);

        ArancelVariante::create([
            'campo_id' => $campo->id,
            'codigo' => 'INACTIVA',
            'nombre' => 'Servicio inactivo',
            'tipo_uso' => 'servicio_adicional',
            'servicio_sireb_id' => '01a141f8-689a-707e-8b90-050c79331f48',
            'servicio_sireb_codigo' => 'SEDEDE-CV-INACTIVA',
            'unidad_medida' => 'servicio',
            'visible_publico' => false,
            'activo' => false,
            'orden' => 40,
        ]);

        TarifaCampo::create([
            'campo_id' => $campo->id,
            'servicio_sireb_id' => $limpieza->servicio_sireb_id,
            'tarifa_sireb_id' => '01a141f9-70f6-716a-a436-035b7121ee48',
            'etiqueta_sireb' => 'Limpieza',
            'liquidable' => true,
            'tarifario_estado' => 'vigente',
            'estado_tarifa_sireb' => 'activo',
            'categorias_sireb' => [],
            'perfil' => null,
            'clasificacion' => 'no_clasificada',
            'precio_por_hora' => 15.00,
            'vigente_desde' => now(),
            'vigente_hasta' => null,
            'creado_por' => null,
        ]);

        $response = app(ArancelVarianteController::class)->serviciosAdicionales($campo);

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(1, $payload['data']);

        $this->assertSame('LIMPIEZA', $payload['data'][0]['codigo']);
        $this->assertSame('servicio', $payload['data'][0]['unidad_medida']);
        $this->assertSame('servicio_adicional', $payload['data'][0]['tipo_uso']);

        $this->assertCount(1, $payload['data'][0]['tarifas']);

        $this->assertEqualsWithDelta(
            15.00,
            (float) $payload['data'][0]['tarifas'][0]['monto'],
            0.001
        );
    }
}

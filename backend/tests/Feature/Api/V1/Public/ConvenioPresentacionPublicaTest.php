<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\ArancelVariante;
use App\Models\Asociacion;
use App\Models\CampoDeportivo;
use App\Models\Ciudadano;
use App\Models\HorarioAtencion;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use App\Services\CatalogoSirebService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * 7a+7b: el catálogo público separa norma (todos) de convenio (solo logueado
 * con asociación vigente del tipo). El anónimo jamas ve precio de convenio.
 */
class ConvenioPresentacionPublicaTest extends TestCase
{
    use RefreshDatabase;

    private const SVC_NORMA = '01a14203-db11-7390-a168-00a6a71833a1';
    private const SVC_CONVENIO = '01a14203-db11-7390-a168-00a6a71833e2';
    private const TARIFA_NORMA_DIURNA = '01a141f9-70f6-716a-a436-035b7121ee01';
    private const TARIFA_CONVENIO_DIURNA = '01a141f9-70f6-716a-a436-035b7121ee47';

    public function test_anonimo_ve_norma_y_nunca_convenio(): void
    {
        $this->mockCatalogoSirebLocalFallback();
        $campo = $this->crearCampoConNormaYConvenio();

        $res = $this->getJson('/api/v1/public/campos');
        $res->assertOk();

        $item = collect($res->json('data'))->firstWhere('id', $campo->id);
        $this->assertNotNull($item, 'El campo con norma debe aparecer para el anónimo');
        $this->assertTrue($item['reservable_online']);
        // Norma visible: 100 (la diurna de norma).
        $this->assertSame(100.0, (float) $item['tarifas']['diurna']['precio_por_hora']);
        // Convenio NUNCA revelado al anónimo.
        $this->assertNull($item['convenio']);
    }

    public function test_asociado_municipal_logueado_ve_norma_y_convenio(): void
    {
        $this->mockCatalogoSirebLocalFallback();
        $ci = '7654501';
        $asociacion = Asociacion::create([
            'nit' => 'NIT-'.$ci, 'nombre' => 'Asoc '.$ci, 'tipo' => 'municipal',
            'convenio_numero' => 'CONV-'.$ci,
            'convenio_desde' => now()->subMonth()->toDateString(),
            'convenio_hasta' => now()->addYear()->toDateString(),
            'responsable_nombre' => 'Resp', 'responsable_ci' => $ci,
            'responsable_telefono' => '70000000', 'activo' => true,
        ]);
        $ciudadano = new Ciudadano();
        $ciudadano->ci = $ci; $ciudadano->nombre_completo = 'Pres'; $ciudadano->telefono = '70000000';
        $ciudadano->password_hash = bcrypt('s'); $ciudadano->es_asociacion = true;
        $ciudadano->asociacion_id = $asociacion->id; $ciudadano->save();

        $campo = $this->crearCampoConNormaYConvenio();

        Sanctum::actingAs($ciudadano);
        $res = $this->getJson('/api/v1/public/campos');
        $item = collect($res->json('data'))->firstWhere('id', $campo->id);

        // Norma sigue visible (100) y convenio revelado (25).
        $this->assertSame(100.0, (float) $item['tarifas']['diurna']['precio_por_hora']);
        $this->assertNotNull($item['convenio']);
        $this->assertTrue($item['convenio']['visible']);
        $this->assertSame(25.0, (float) $item['convenio']['precio_min']);
    }

    public function test_asociado_departamental_no_ve_convenio_municipal_del_campo(): void
    {
        $this->mockCatalogoSirebLocalFallback();
        $ci = '7654502';
        $asociacion = Asociacion::create([
            'nit' => 'NIT-'.$ci, 'nombre' => 'Asoc '.$ci, 'tipo' => 'departamental',
            'convenio_numero' => 'CONV-'.$ci,
            'convenio_desde' => now()->subMonth()->toDateString(),
            'convenio_hasta' => now()->addYear()->toDateString(),
            'responsable_nombre' => 'Resp', 'responsable_ci' => $ci,
            'responsable_telefono' => '70000000', 'activo' => true,
        ]);
        $ciudadano = new Ciudadano();
        $ciudadano->ci = $ci; $ciudadano->nombre_completo = 'Pres'; $ciudadano->telefono = '70000000';
        $ciudadano->password_hash = bcrypt('s'); $ciudadano->es_asociacion = true;
        $ciudadano->asociacion_id = $asociacion->id; $ciudadano->save();

        $campo = $this->crearCampoConNormaYConvenio(); // solo convenio MUNICIPAL

        Sanctum::actingAs($ciudadano);
        $res = $this->getJson('/api/v1/public/campos');
        $item = collect($res->json('data'))->firstWhere('id', $campo->id);

        // Departamental en campo con solo convenio municipal -> no ve convenio.
        $this->assertNull($item['convenio']);
        $this->assertSame(100.0, (float) $item['tarifas']['diurna']['precio_por_hora']);
    }

    /**
     * [7a-fix-test] Fuerza LOCAL_FALLBACK mockeando el catálogo. En el entorno
     * local del operador Memurai puede tener caché del catálogo SIREB REAL, y
     * los uuids fake de este test (SVC_NORMA/SVC_CONVENIO) no existen ahí ->
     * el presenter caería a noVinculado/desdeConvenioSolo en vez de desdeLocal
     * con la norma. En CI (sin caché) catalogoCacheado lanza y ya da
     * LOCAL_FALLBACK; el mock hace el test determinístico en ambos.
     */
    private function mockCatalogoSirebLocalFallback(): void
    {
        $mock = Mockery::mock(CatalogoSirebService::class);
        $mock->shouldReceive('catalogoCacheado')
            ->andThrow(new \RuntimeException('catálogo no disponible en test'));
        $mock->shouldReceive('ultimaActualizacion')->andReturn(null);

        $this->instance(CatalogoSirebService::class, $mock);
    }

    private function crearCampoConNormaYConvenio(): CampoDeportivo
    {
        $tipo = TipoCampo::firstOrCreate(['nombre' => 'Fútbol'], ['estado' => 'activo']);
        $fecha = Carbon::now('America/La_Paz')->addDay();

        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id, 'codigo' => 'PRES-'.uniqid(),
            'nombre' => 'Cancha Presentacion', 'direccion' => 'Trinidad',
            'latitud' => -14.84, 'longitud' => -64.90, 'estado' => 'activo',
            'modalidad_reserva' => \App\Enums\ModalidadReservaCampo::FranjaHoraria,
            'hora_inicio_noche' => '18:00:00',
            'servicio_sireb_id' => null, // solo-variantes: la norma es variante visible_publico=true
        ]);
        HorarioAtencion::create([
            'campo_id' => $campo->id, 'dia_semana' => $fecha->dayOfWeekIso,
            'hora_apertura' => '08:00', 'hora_cierre' => '20:00',
        ]);

        // Norma pública (visible_publico=true, tipo_asociacion null).
        $norma = ArancelVariante::create([
            'campo_id' => $campo->id, 'codigo' => 'BASE', 'nombre' => 'Alquiler base',
            'tipo_uso' => 'alquiler_base', 'servicio_sireb_id' => self::SVC_NORMA,
            'servicio_sireb_codigo' => 'SEDEDE-PRES-BASE', 'unidad_medida' => 'hora',
            'visible_publico' => true, 'activo' => true, 'orden' => 1,
        ]);
        $norma->tipo_asociacion = null; $norma->save();

        // Convenio municipal (visible_publico=false, tipo_asociacion=municipal).
        $convenio = ArancelVariante::create([
            'campo_id' => $campo->id, 'codigo' => 'MUNI', 'nombre' => 'Convenio Municipal',
            'tipo_uso' => 'asociacion', 'servicio_sireb_id' => self::SVC_CONVENIO,
            'servicio_sireb_codigo' => 'SEDEDE-PRES-MUNI', 'unidad_medida' => 'hora',
            'visible_publico' => false, 'activo' => true, 'orden' => 2,
        ]);
        $convenio->tipo_asociacion = 'municipal'; $convenio->save();

        TarifaCampo::create([
            'campo_id' => $campo->id, 'servicio_sireb_id' => self::SVC_NORMA,
            'tarifa_sireb_id' => self::TARIFA_NORMA_DIURNA, 'etiqueta_sireb' => 'Diurno',
            'liquidable' => true, 'tarifario_estado' => 'vigente', 'estado_tarifa_sireb' => 'activo',
            'categorias_sireb' => [], 'perfil' => null, 'clasificacion' => 'diurna',
            'precio_por_hora' => 100.00, 'vigente_desde' => now(), 'vigente_hasta' => null, 'creado_por' => null,
        ]);
        TarifaCampo::create([
            'campo_id' => $campo->id, 'servicio_sireb_id' => self::SVC_CONVENIO,
            'tarifa_sireb_id' => self::TARIFA_CONVENIO_DIURNA, 'etiqueta_sireb' => 'Diurno',
            'liquidable' => true, 'tarifario_estado' => 'vigente', 'estado_tarifa_sireb' => 'activo',
            'categorias_sireb' => [], 'perfil' => null, 'clasificacion' => 'diurna',
            'precio_por_hora' => 25.00, 'vigente_desde' => now(), 'vigente_hasta' => null, 'creado_por' => null,
        ]);

        return $campo;
    }
}

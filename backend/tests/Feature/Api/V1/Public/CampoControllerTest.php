<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\CampoDeportivo;
use App\Models\HorarioAtencion;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoints públicos de consulta de campos deportivos.
 * Sin autenticación: ciudadanos anónimos.
 */
class CampoControllerTest extends TestCase
{
    use RefreshDatabase;

    private TipoCampo $tipoFutbol;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear tipo una sola vez para todos los tests
        $this->tipoFutbol = TipoCampo::create([
            'nombre' => 'Fútbol Test',
            'estado' => 'activo',
        ]);
    }

    /**
     * Helper: crea un campo deportivo con al menos un horario de atención.
     */
    private function crearCampo(array $attrs = []): CampoDeportivo
    {
        $campo = CampoDeportivo::create(array_merge([
            'tipo_campo_id' => $this->tipoFutbol->id,
            'codigo' => 'TEST-' . uniqid(),
            'nombre' => 'Campo Test ' . uniqid(),
            'direccion' => 'Dirección de prueba',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ], $attrs));

        // Un horario para el lunes (día 1)
        HorarioAtencion::create([
            'campo_id' => $campo->id,
            'dia_semana' => 1,
            'hora_apertura' => '08:00',
            'hora_cierre' => '20:00',
        ]);

        return $campo;
    }

    public function test_campo_inactivo_no_aparece_en_listado_publico(): void
    {
        $activo = $this->crearCampo(['estado' => 'activo']);
        $inactivo = $this->crearCampo(['estado' => 'inactivo']);

        $response = $this->getJson('/api/v1/public/campos');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        
        $this->assertContains($activo->id, $ids, 'El campo activo debe aparecer');
        $this->assertNotContains($inactivo->id, $ids, 'El campo inactivo NO debe aparecer');
    }

    public function test_campo_mantenimiento_aparece_con_estado_visible(): void
    {
        $mantenimiento = $this->crearCampo(['estado' => 'mantenimiento']);

        $response = $this->getJson('/api/v1/public/campos');

        $response->assertOk();
        $campo = collect($response->json('data'))->firstWhere('id', $mantenimiento->id);
        $this->assertNotNull($campo, 'El campo en mantenimiento debe aparecer en el listado público');
        $this->assertEquals('mantenimiento', $campo['estado']);
    }

    public function test_listado_no_requiere_token(): void
    {
        $this->crearCampo();

        // Sin header Authorization
        $response = $this->getJson('/api/v1/public/campos');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'nombre', 'estado']]]);
    }

    public function test_detalle_no_requiere_token(): void
    {
        $campo = $this->crearCampo();

        // Sin header Authorization
        $response = $this->getJson("/api/v1/public/campos/{$campo->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id', 'nombre', 'direccion', 'latitud', 'longitud',
                'estado', 'tipo_campo', 'horarios_atencion',
            ],
        ]);
    }

    public function test_json_publico_no_contiene_campos_de_auditoria(): void
    {
        $campo = $this->crearCampo();

        $response = $this->getJson("/api/v1/public/campos/{$campo->id}");

        $response->assertOk();
        $data = $response->json('data');

        // Estos NUNCA deben salir en el JSON público
        $this->assertArrayNotHasKey('creado_en', $data);
        $this->assertArrayNotHasKey('actualizado_en', $data);
        $this->assertArrayNotHasKey('created_at', $data);
        $this->assertArrayNotHasKey('updated_at', $data);
        $this->assertArrayNotHasKey('codigo', $data); // código interno
    }

    public function test_campo_inactivo_devuelve_404_en_detalle(): void
    {
        $inactivo = $this->crearCampo(['estado' => 'inactivo']);

        $response = $this->getJson("/api/v1/public/campos/{$inactivo->id}");

        $response->assertNotFound();
    }
}
<?php

namespace Tests\Feature\Api\V1;

use App\Models\Auditoria;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Rol;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TarifaCampoTest extends TestCase
{
    use RefreshDatabase;

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
        ]);
    }

    public function test_crear_segunda_tarifa_cierra_la_anterior(): void
    {
        Sanctum::actingAs($this->admin);

        // Crear primera tarifa
        $response1 = $this->postJson("/api/v1/campos-deportivos/{$this->campo->id}/tarifas", [
            'precio_por_hora' => 100.00,
        ]);

        $response1->assertStatus(201)
            ->assertJsonPath('data.precio_por_hora', '100.00');

        $tarifa1 = TarifaCampo::first();
        $this->assertNull($tarifa1->vigente_hasta);

        // Esperar 1 segundo para que las fechas sean distintas
        sleep(1);

        // Crear segunda tarifa (debe cerrar la primera)
        $response2 = $this->postJson("/api/v1/campos-deportivos/{$this->campo->id}/tarifas", [
            'precio_por_hora' => 150.00,
        ]);

        $response2->assertStatus(201)
            ->assertJsonPath('data.precio_por_hora', '150.00');

        // Verificar que la primera tarifa ahora tiene vigente_hasta poblado
        $tarifa1Fresh = $tarifa1->fresh();
        $this->assertNotNull($tarifa1Fresh->vigente_hasta);

        // Verificar que la segunda tarifa está activa
        $tarifa2 = TarifaCampo::where('precio_por_hora', 150.00)->first();
        $this->assertNull($tarifa2->vigente_hasta);

        // Verificar que se generaron 2 filas de auditoría
        $this->assertEquals(2, Auditoria::where('tabla', 'tarifas_campo')->count());
    }

    public function test_indice_unico_parcial_impide_dos_tarifas_activas(): void
    {
        // Intentar insertar dos tarifas activas saltándose el Service
        // Debe fallar por el índice único parcial uq_tarifa_activa
        $this->expectException(\Illuminate\Database\QueryException::class);

        TarifaCampo::create([
            'campo_id' => $this->campo->id,
            'precio_por_hora' => 100.00,
            'vigente_desde' => now(),
            'vigente_hasta' => null,
            'creado_por' => $this->admin->id,
        ]);

        // Esta segunda inserción debe fallar por el índice único
        TarifaCampo::create([
            'campo_id' => $this->campo->id,
            'precio_por_hora' => 150.00,
            'vigente_desde' => now(),
            'vigente_hasta' => null, // Ambas activas → violación del índice
            'creado_por' => $this->admin->id,
        ]);
    }

    public function test_historial_de_tarifas(): void
    {
        Sanctum::actingAs($this->admin);

        // Crear 3 tarifas
        $this->postJson("/api/v1/campos-deportivos/{$this->campo->id}/tarifas", [
            'precio_por_hora' => 100.00,
        ]);

        sleep(1);

        $this->postJson("/api/v1/campos-deportivos/{$this->campo->id}/tarifas", [
            'precio_por_hora' => 150.00,
        ]);

        sleep(1);

        $this->postJson("/api/v1/campos-deportivos/{$this->campo->id}/tarifas", [
            'precio_por_hora' => 200.00,
        ]);

        // Ver historial
        $response = $this->getJson("/api/v1/campos-deportivos/{$this->campo->id}/tarifas");

        $response->assertOk()
            ->assertJsonCount(3, 'data.historial')
            ->assertJsonPath('data.activa.precio_por_hora', '200.00');

        // La tarifa activa debe tener vigente_hasta null
        $this->assertNull($response->json('data.activa.vigente_hasta'));
    }

    public function test_cambiar_estado_de_campo_registra_auditoria(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/campos-deportivos/{$this->campo->id}/estado", [
            'estado' => 'mantenimiento',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.estado', 'mantenimiento');

        // Verificar que se generó una fila de auditoría
        $auditoria = Auditoria::where('tabla', 'campos_deportivos')
            ->where('registro_id', $this->campo->id)
            ->where('accion', 'cambiar_estado')
            ->first();

        $this->assertNotNull($auditoria);
        $this->assertEquals('activo', $auditoria->datos_anteriores['estado']);
        $this->assertEquals('mantenimiento', $auditoria->datos_nuevos['estado']);
        $this->assertEquals($this->admin->id, $auditoria->usuario_id);
    }
}

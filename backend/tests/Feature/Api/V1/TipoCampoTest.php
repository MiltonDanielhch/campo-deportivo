<?php

namespace Tests\Feature\Api\V1;

use App\Models\Funcionario;
use App\Models\Rol;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipoCampoTest extends TestCase
{
    use RefreshDatabase;

    private Funcionario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Rol::create([
            'nombre' => 'admin_parametricas',
            'descripcion' => 'Administrador de paramétricas',
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
    }

    public function test_crear_tipo_campo(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/tipos-campo', [
            'nombre' => 'Fútbol',
            'descripcion' => 'Cancha de fútbol profesional',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Fútbol')
            ->assertJsonPath('data.estado', 'activo');

        $this->assertDatabaseHas('tipos_campo', [
            'nombre' => 'Fútbol',
            'estado' => 'activo',
        ]);
    }

    public function test_listar_tipos_campo(): void
    {
        TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);
        TipoCampo::create(['nombre' => 'Básquet', 'estado' => 'activo']);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/tipos-campo');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_actualizar_tipo_campo(): void
    {
        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);

        Sanctum::actingAs($this->admin);

        $response = $this->putJson("/api/v1/tipos-campo/{$tipo->id}", [
            'nombre' => 'Fútbol 11',
            'descripcion' => 'Fútbol profesional 11 jugadores',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.nombre', 'Fútbol 11');

        $this->assertDatabaseHas('tipos_campo', [
            'id' => $tipo->id,
            'nombre' => 'Fútbol 11',
        ]);
    }

    public function test_inhabilitar_tipo_campo(): void
    {
        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);

        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/tipos-campo/{$tipo->id}/inhabilitar");

        $response->assertOk()
            ->assertJsonPath('data.estado', 'inactivo');

        $this->assertDatabaseHas('tipos_campo', [
            'id' => $tipo->id,
            'estado' => 'inactivo',
        ]);
    }

    public function test_nombre_duplicado_falla(): void
    {
        TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/tipos-campo', [
            'nombre' => 'Fútbol',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    public function test_sin_autenticacion_devuelve_401(): void
    {
        $response = $this->getJson('/api/v1/tipos-campo');

        $response->assertUnauthorized();
    }
}

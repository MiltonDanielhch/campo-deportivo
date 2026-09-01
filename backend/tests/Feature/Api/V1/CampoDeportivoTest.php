<?php

namespace Tests\Feature\Api\V1;

use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Rol;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampoDeportivoTest extends TestCase
{
    use RefreshDatabase;

    private Funcionario $admin;
    private TipoCampo $tipoCampo;

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

        $this->tipoCampo = TipoCampo::create([
            'nombre' => 'Fútbol',
            'estado' => 'activo',
        ]);
    }

    private function horariosValidos(): array
    {
        return [
            ['dia_semana' => 1, 'hora_apertura' => '08:00', 'hora_cierre' => '20:00'],
            ['dia_semana' => 2, 'hora_apertura' => '08:00', 'hora_cierre' => '20:00'],
            ['dia_semana' => 3, 'hora_apertura' => '08:00', 'hora_cierre' => '20:00'],
            ['dia_semana' => 4, 'hora_apertura' => '08:00', 'hora_cierre' => '20:00'],
            ['dia_semana' => 5, 'hora_apertura' => '08:00', 'hora_cierre' => '20:00'],
            ['dia_semana' => 6, 'hora_apertura' => '09:00', 'hora_cierre' => '18:00'],
            ['dia_semana' => 7, 'hora_apertura' => '09:00', 'hora_cierre' => '18:00'],
        ];
    }

    public function test_crear_campo_con_7_horarios(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/campos-deportivos', [
            'tipo_campo_id' => $this->tipoCampo->id,
            'codigo' => 'CD-001',
            'nombre' => 'Cancha Central',
            'direccion' => 'Av. Principal #123',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'horarios' => $this->horariosValidos(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.codigo', 'CD-001')
            ->assertJsonPath('data.estado', 'activo');

        $this->assertDatabaseHas('campos_deportivos', ['codigo' => 'CD-001']);
        $this->assertDatabaseCount('horarios_atencion', 7);
    }

    public function test_codigo_duplicado_falla_422(): void
    {
        CampoDeportivo::create([
            'tipo_campo_id' => $this->tipoCampo->id,
            'codigo' => 'CD-001',
            'nombre' => 'Cancha Central',
            'direccion' => 'Av. Principal',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/campos-deportivos', [
            'tipo_campo_id' => $this->tipoCampo->id,
            'codigo' => 'CD-001',
            'nombre' => 'Cancha Duplicada',
            'direccion' => 'Otra dirección',
            'latitud' => -14.8500,
            'longitud' => -64.9100,
            'horarios' => $this->horariosValidos(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('codigo');
    }

    public function test_horarios_con_dia_duplicado_falla_422(): void
    {
        Sanctum::actingAs($this->admin);

        $horariosDuplicados = $this->horariosValidos();
        $horariosDuplicados[1]['dia_semana'] = 1; // Dos lunes

        $response = $this->postJson('/api/v1/campos-deportivos', [
            'tipo_campo_id' => $this->tipoCampo->id,
            'codigo' => 'CD-002',
            'nombre' => 'Cancha Inválida',
            'direccion' => 'Calle Falsa',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'horarios' => $horariosDuplicados,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('horarios');
    }

    public function test_coordenadas_fuera_del_beni_falla_422(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/campos-deportivos', [
            'tipo_campo_id' => $this->tipoCampo->id,
            'codigo' => 'CD-003',
            'nombre' => 'Cancha Fuera de Rango',
            'direccion' => 'La Paz',
            'latitud' => -16.5,     // Ok para La Paz pero fuera de rango esperado
            'longitud' => -68.15,   // Fuera del rango del Beni
            'horarios' => $this->horariosValidos(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('longitud');
    }

    public function test_cambiar_estado_a_mantenimiento(): void
    {
        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $this->tipoCampo->id,
            'codigo' => 'CD-001',
            'nombre' => 'Cancha Central',
            'direccion' => 'Av. Principal',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/campos-deportivos/{$campo->id}/estado", [
            'estado' => 'mantenimiento',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.estado', 'mantenimiento');

        $this->assertDatabaseHas('campos_deportivos', [
            'id' => $campo->id,
            'estado' => 'mantenimiento',
        ]);
    }

    public function test_listar_con_filtros(): void
    {
        CampoDeportivo::create([
            'tipo_campo_id' => $this->tipoCampo->id,
            'codigo' => 'CD-001',
            'nombre' => 'Cancha A',
            'direccion' => 'Calle A',
            'latitud' => -14.8432,
            'longitud' => -64.9012,
            'estado' => 'activo',
        ]);
        CampoDeportivo::create([
            'tipo_campo_id' => $this->tipoCampo->id,
            'codigo' => 'CD-002',
            'nombre' => 'Cancha B',
            'direccion' => 'Calle B',
            'latitud' => -14.8500,
            'longitud' => -64.9100,
            'estado' => 'mantenimiento',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/campos-deportivos?estado=activo');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }
}

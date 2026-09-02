<?php

namespace Tests\Feature\Api\V1;

use App\Models\Auditoria;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Rol;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AsignacionFuncionarioTest extends TestCase
{
    use RefreshDatabase;

    private Funcionario $admin;
    private Rol $rolAdmin;
    private Rol $rolControl;
    private Rol $rolGerencia;
    private CampoDeportivo $campo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rolAdmin = Rol::create([
            'nombre' => 'admin_parametricas',
            'descripcion' => 'Admin paramétricas',
            'permisos' => ['*'],
        ]);

        $this->rolControl = Rol::create([
            'nombre' => 'funcionario_control',
            'descripcion' => 'Funcionario de control',
            'permisos' => [],
        ]);

        $this->rolGerencia = Rol::create([
            'nombre' => 'gerencia',
            'descripcion' => 'Gerencia',
            'permisos' => [],
        ]);

        $this->admin = Funcionario::create([
            'nombre_completo' => 'Admin Test',
            'ci' => '12345678',
            'usuario' => 'admin_test',
            'password_hash' => bcrypt('secret'),
            'rol_id' => $this->rolAdmin->id,
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

    private function crearFuncionarioConRol(Rol $rol, string $ci, string $usuario): Funcionario
    {
        return Funcionario::create([
            'nombre_completo' => "Funcionario $usuario",
            'ci' => $ci,
            'usuario' => $usuario,
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);
    }

    public function test_asignar_campo_a_funcionario_control(): void
    {
        $funcionario = $this->crearFuncionarioConRol($this->rolControl, '11111111', 'control.uno');

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/funcionarios/{$funcionario->id}/campos/{$this->campo->id}");

        $response->assertStatus(201)
            ->assertJsonPath('data.funcionario_id', $funcionario->id)
            ->assertJsonPath('data.campo_id', $this->campo->id);

        // Verificar que existe la asignación en BD
        $this->assertDatabaseHas('asignaciones_funcionario', [
            'funcionario_id' => $funcionario->id,
            'campo_id' => $this->campo->id,
        ]);

        // Verificar auditoría
        $this->assertEquals(1, Auditoria::where('tabla', 'asignaciones_funcionario')
            ->where('accion', 'asignar')
            ->count());
    }

    public function test_asignar_campo_a_gerencia_falla_422(): void
    {
        $funcionario = $this->crearFuncionarioConRol($this->rolGerencia, '22222222', 'gerencia.uno');

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/funcionarios/{$funcionario->id}/campos/{$this->campo->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors('funcionario_id');

        // Verificar que NO se creó la asignación
        $this->assertDatabaseMissing('asignaciones_funcionario', [
            'funcionario_id' => $funcionario->id,
            'campo_id' => $this->campo->id,
        ]);
    }

    public function test_asignar_campo_a_admin_parametricas_falla_422(): void
    {
        $funcionario = $this->crearFuncionarioConRol($this->rolAdmin, '33333333', 'admin.dos');

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/funcionarios/{$funcionario->id}/campos/{$this->campo->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors('funcionario_id');
    }

    public function test_asignar_dos_veces_no_duplica(): void
    {
        $funcionario = $this->crearFuncionarioConRol($this->rolControl, '11111111', 'control.uno');

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/funcionarios/{$funcionario->id}/campos/{$this->campo->id}")->assertStatus(201);
        $this->postJson("/api/v1/funcionarios/{$funcionario->id}/campos/{$this->campo->id}")->assertStatus(201);

        // Solo debe existir UNA asignación
        $this->assertEquals(1, \App\Models\AsignacionFuncionario::where('funcionario_id', $funcionario->id)
            ->where('campo_id', $this->campo->id)
            ->count());
    }

    public function test_desasignar_campo(): void
    {
        $funcionario = $this->crearFuncionarioConRol($this->rolControl, '11111111', 'control.uno');

        Sanctum::actingAs($this->admin);

        // Asignar primero
        $this->postJson("/api/v1/funcionarios/{$funcionario->id}/campos/{$this->campo->id}")->assertStatus(201);

        // Desasignar
        $response = $this->deleteJson("/api/v1/funcionarios/{$funcionario->id}/campos/{$this->campo->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Campo desasignado correctamente');

        // Verificar que ya no existe la asignación
        $this->assertDatabaseMissing('asignaciones_funcionario', [
            'funcionario_id' => $funcionario->id,
            'campo_id' => $this->campo->id,
        ]);

        // Verificar auditoría de desasignación
        $this->assertEquals(1, Auditoria::where('tabla', 'asignaciones_funcionario')
            ->where('accion', 'desasignar')
            ->count());
    }

    public function test_listar_campos_de_un_funcionario(): void
    {
        $funcionario = $this->crearFuncionarioConRol($this->rolControl, '11111111', 'control.uno');

        // Crear un segundo campo
        $tipo = TipoCampo::create(['nombre' => 'Básquet', 'estado' => 'activo']);
        $campo2 = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CD-TEST-2',
            'nombre' => 'Cancha Test 2',
            'direccion' => 'Trinidad',
            'latitud' => -14.8500,
            'longitud' => -64.9100,
            'estado' => 'activo',
        ]);

        Sanctum::actingAs($this->admin);

        // Asignar ambos campos
        $this->postJson("/api/v1/funcionarios/{$funcionario->id}/campos/{$this->campo->id}")->assertStatus(201);
        $this->postJson("/api/v1/funcionarios/{$funcionario->id}/campos/{$campo2->id}")->assertStatus(201);

        // Listar
        $response = $this->getJson("/api/v1/funcionarios/{$funcionario->id}/campos");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_listar_todas_las_asignaciones(): void
    {
        $funcionario = $this->crearFuncionarioConRol($this->rolControl, '11111111', 'control.uno');

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/funcionarios/{$funcionario->id}/campos/{$this->campo->id}")->assertStatus(201);

        $response = $this->getJson('/api/v1/asignaciones');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.usuario', 'control.uno')
            ->assertJsonCount(1, 'data.0.campos_asignados');
    }
}

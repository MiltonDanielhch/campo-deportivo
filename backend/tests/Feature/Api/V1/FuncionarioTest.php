<?php

namespace Tests\Feature\Api\V1;

use App\Models\Auditoria;
use App\Models\Funcionario;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FuncionarioTest extends TestCase
{
    use RefreshDatabase;

    private Funcionario $admin;
    private Rol $rolAdmin;
    private Rol $rolControl;
    private Rol $rolGerencia;

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
    }

    public function test_crear_funcionario_guarda_contrasena_hasheada_y_audita(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/funcionarios', [
            'nombre_completo' => 'Juan Pérez',
            'ci' => '87654321',
            'usuario' => 'juan.perez',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'rol_id' => $this->rolControl->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.nombre_completo', 'Juan Pérez')
            ->assertJsonPath('data.estado', 'activo');

        // Verificar que la contraseña está hasheada
        $funcionario = Funcionario::where('ci', '87654321')->first();
        $this->assertNotEquals('password123', $funcionario->password_hash);
        $this->assertTrue(Hash::check('password123', $funcionario->password_hash));

        // Verificar que se generó auditoría
        $auditoria = Auditoria::where('tabla', 'funcionarios')
            ->where('registro_id', $funcionario->id)
            ->where('accion', 'crear')
            ->first();

        $this->assertNotNull($auditoria);
        $this->assertEquals($this->admin->id, $auditoria->usuario_id);
    }

    public function test_crear_funcionario_con_ci_duplicado_falla_422(): void
    {
        Funcionario::create([
            'nombre_completo' => 'Funcionario Existente',
            'ci' => '87654321',
            'usuario' => 'existente',
            'password_hash' => bcrypt('secret'),
            'rol_id' => $this->rolControl->id,
            'estado' => 'activo',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/funcionarios', [
            'nombre_completo' => 'Otro Funcionario',
            'ci' => '87654321',
            'usuario' => 'otro.usuario',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'rol_id' => $this->rolControl->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('ci');
    }

    public function test_crear_funcionario_con_usuario_duplicado_falla_422(): void
    {
        Funcionario::create([
            'nombre_completo' => 'Funcionario Existente',
            'ci' => '11111111',
            'usuario' => 'juan.perez',
            'password_hash' => bcrypt('secret'),
            'rol_id' => $this->rolControl->id,
            'estado' => 'activo',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/funcionarios', [
            'nombre_completo' => 'Otro Funcionario',
            'ci' => '22222222',
            'usuario' => 'juan.perez',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'rol_id' => $this->rolControl->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('usuario');
    }

    public function test_inactivar_funcionario_registra_auditoria(): void
    {
        $funcionario = Funcionario::create([
            'nombre_completo' => 'Juan Pérez',
            'ci' => '87654321',
            'usuario' => 'juan.perez',
            'password_hash' => bcrypt('secret'),
            'rol_id' => $this->rolControl->id,
            'estado' => 'activo',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/funcionarios/{$funcionario->id}/estado", [
            'estado' => 'inactivo',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.estado', 'inactivo');

        // Verificar auditoría
        $auditoria = Auditoria::where('tabla', 'funcionarios')
            ->where('registro_id', $funcionario->id)
            ->where('accion', 'cambiar_estado')
            ->first();

        $this->assertNotNull($auditoria);
        $this->assertEquals('activo', $auditoria->datos_anteriores['estado']);
        $this->assertEquals('inactivo', $auditoria->datos_nuevos['estado']);
    }

    public function test_listar_funcionarios_con_filtro_por_rol(): void
    {
        Funcionario::create([
            'nombre_completo' => 'Control Uno',
            'ci' => '11111111',
            'usuario' => 'control.uno',
            'password_hash' => bcrypt('secret'),
            'rol_id' => $this->rolControl->id,
            'estado' => 'activo',
        ]);

        Funcionario::create([
            'nombre_completo' => 'Gerencia Uno',
            'ci' => '22222222',
            'usuario' => 'gerencia.uno',
            'password_hash' => bcrypt('secret'),
            'rol_id' => $this->rolGerencia->id,
            'estado' => 'activo',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/v1/funcionarios?rol_id={$this->rolControl->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rol.nombre', 'funcionario_control');
    }

    public function test_login_falla_con_funcionario_inactivo(): void
    {
        Funcionario::create([
            'nombre_completo' => 'Juan Inactivo',
            'ci' => '87654321',
            'usuario' => 'juan.inactivo',
            'password_hash' => bcrypt('secret123'),
            'rol_id' => $this->rolControl->id,
            'estado' => 'inactivo',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'usuario' => 'juan.inactivo',
            'password' => 'secret123',
        ]);

        $response->assertUnauthorized();
    }
}

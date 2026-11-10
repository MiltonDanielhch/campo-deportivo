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
            'password' => 'Admin2026!',
            'password_confirmation' => 'Admin2026!',
            'rol_id' => $this->rolControl->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.nombre_completo', 'Juan Pérez')
            ->assertJsonPath('data.estado', 'activo');

        // Verificar que la contraseña está hasheada
        $funcionario = Funcionario::where('ci', '87654321')->first();
        $this->assertNotEquals('Admin2026!', $funcionario->password_hash);
        $this->assertTrue(Hash::check('Admin2026!', $funcionario->password_hash));

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
            'password' => 'Admin2026!',
            'password_confirmation' => 'Admin2026!',
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
            'password' => 'Admin2026!',
            'password_confirmation' => 'Admin2026!',
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

    public function test_actualiza_nombre_y_rol_sin_cambiar_password(): void
    {
        $f = Funcionario::create([
            'nombre_completo' => 'Juan Pérez',
            'ci' => '87654321',
            'usuario' => 'juan.perez',
            'password_hash' => bcrypt('Vieja123!'),
            'rol_id' => $this->rolControl->id,
            'estado' => 'activo',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->putJson("/api/v1/funcionarios/{$f->id}", [
            'nombre_completo' => 'Juan Pérez Actualizado',
            'ci' => '87654321',
            'usuario' => 'juan.perez',
            'rol_id' => $this->rolGerencia->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.nombre_completo', 'Juan Pérez Actualizado');

        $f->refresh();
        $this->assertTrue(Hash::check('Vieja123!', $f->password_hash));
    }

    public function test_password_nueva_se_hashea_y_audita(): void
    {
        $f = Funcionario::create([
            'nombre_completo' => 'Juan Pérez',
            'ci' => '87654321',
            'usuario' => 'juan.perez',
            'password_hash' => bcrypt('Vieja123!'),
            'rol_id' => $this->rolControl->id,
            'estado' => 'activo',
        ]);

        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/funcionarios/{$f->id}", [
            'nombre_completo' => 'Juan Pérez',
            'ci' => '87654321',
            'usuario' => 'juan.perez',
            'rol_id' => $this->rolControl->id,
            'password' => 'Nueva123!',
            'password_confirmation' => 'Nueva123!',
        ])->assertOk();

        $f->refresh();
        $this->assertTrue(Hash::check('Nueva123!', $f->password_hash));
        $this->assertFalse(Hash::check('Vieja123!', $f->password_hash));

        $auditoria = Auditoria::where('tabla', 'funcionarios')
            ->where('registro_id', $f->id)
            ->where('accion', 'actualizar')
            ->first();
        $this->assertNotNull($auditoria);
    }

    public function test_no_degrada_al_ultimo_admin_parametricas(): void
    {
        // $this->admin es el único admin_parametricas activo (setUp).
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/funcionarios/{$this->admin->id}", [
            'nombre_completo' => 'Admin Test',
            'ci' => '12345678',
            'usuario' => 'admin_test',
            'rol_id' => $this->rolControl->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rol_id');

        $this->admin->refresh();
        $this->assertEquals($this->rolAdmin->id, $this->admin->rol_id);
    }

    public function test_si_puede_degradar_cuando_hay_otro_admin_activo(): void
    {
        Funcionario::create([
            'nombre_completo' => 'Segundo Admin',
            'ci' => '99999999',
            'usuario' => 'admin2',
            'password_hash' => bcrypt('secret'),
            'rol_id' => $this->rolAdmin->id,
            'estado' => 'activo',
        ]);

        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/funcionarios/{$this->admin->id}", [
            'nombre_completo' => 'Admin Test',
            'ci' => '12345678',
            'usuario' => 'admin_test',
            'rol_id' => $this->rolGerencia->id,
        ])->assertOk();

        $this->admin->refresh();
        $this->assertEquals($this->rolGerencia->id, $this->admin->rol_id);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Funcionario;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function crearFuncionario(array $overrides = []): Funcionario
    {
        $rol = Rol::create([
            'nombre' => 'admin_parametricas',
            'descripcion' => 'Rol de prueba',
            'permisos' => ['*'],
        ]);

        return Funcionario::create(array_merge([
            'nombre_completo' => 'Funcionario de Prueba',
            'ci' => '12345678',
            'usuario' => 'fprueba',
            'password_hash' => Hash::make('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ], $overrides));
    }

    public function test_login_correcto_devuelve_200_y_token(): void
    {
        $this->crearFuncionario();

        $this->postJson('/api/v1/auth/login', [
            'usuario' => 'fprueba',
            'password' => 'secret',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'funcionario' => ['id', 'usuario', 'rol' => ['nombre']],
            ]);
    }

    public function test_login_con_funcionario_inactivo_es_rechazado_aunque_la_contrasena_sea_correcta(): void
    {
        $this->crearFuncionario(['estado' => 'inactivo']);

        $this->postJson('/api/v1/auth/login', [
            'usuario' => 'fprueba',
            'password' => 'secret',
        ])->assertUnauthorized();
    }

    public function test_ruta_protegida_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_ruta_protegida_con_token_devuelve_el_funcionario(): void
    {
        $funcionario = $this->crearFuncionario();
        $token = $funcionario->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('funcionario.usuario', 'fprueba');
    }
}

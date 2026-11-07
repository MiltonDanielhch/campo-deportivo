<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Asociacion;
use App\Models\AsociacionAlumno;
use App\Models\Funcionario;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fase 5: admin-fallback de alumnos. Espejo de AsociacionAlumnoTest (ciudadano)
 * con la fuente de asociacion_id = route param (model binding) en vez de token.
 * Regla de oro: AsociacionAlumnoTest (7) y ConvenioAlumnosVentanillaTest (5)
 * verdes sin tocar — el fallback no cambia el presidente ni la ventanilla.
 */
class AsociacionAlumnoAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_crea_alumno_en_asociacion(): void
    {
        $admin = $this->crearAdmin();
        $asociacion = $this->crearAsociacion('A');

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/admin/asociaciones/{$asociacion->id}/alumnos", [
            'ci' => '55550001',
            'nombre_completo' => 'Aluno Uno',
            'fecha_nacimiento' => '2012-05-10',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.ci', '55550001')
            ->assertJsonPath('data.nombre_completo', 'Aluno Uno')
            ->assertJsonPath('data.fecha_nacimiento', '2012-05-10')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('asociacion_alumnos', [
            'asociacion_id' => $asociacion->id,
            'ci' => '55550001',
        ]);
    }

    public function test_admin_no_puede_crear_en_asociacion_inexistente(): void
    {
        $admin = $this->crearAdmin();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/asociaciones/'.Str::uuid()->toString().'/alumnos', [
            'ci' => '55550002',
            'nombre_completo' => 'Huérfano',
        ])->assertNotFound();
    }

    public function test_ci_duplicado_en_la_misma_asociacion_devuelve_422(): void
    {
        $admin = $this->crearAdmin();
        $asociacion = $this->crearAsociacion('A');
        $this->crearAlumno($asociacion->id, '55550003', 'Primero');

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/asociaciones/{$asociacion->id}/alumnos", [
            'ci' => '55550003',
            'nombre_completo' => 'Segundo',
        ])->assertStatus(422)->assertJsonValidationErrors('ci');
    }

    public function test_admin_edita_alumno_de_su_asociacion(): void
    {
        $admin = $this->crearAdmin();
        $asociacion = $this->crearAsociacion('A');
        $alumno = $this->crearAlumno($asociacion->id, '55550004', 'Viejo Nombre');

        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/admin/asociaciones/{$asociacion->id}/alumnos/{$alumno->id}", [
            'ci' => '55550004',
            'nombre_completo' => 'Nuevo Nombre',
            'fecha_nacimiento' => '2011-01-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.nombre_completo', 'Nuevo Nombre')
            ->assertJsonPath('data.fecha_nacimiento', '2011-01-01');
    }

    public function test_admin_no_mueve_alumno_a_otra_asociacion_en_update(): void
    {
        $admin = $this->crearAdmin();
        $asociacionA = $this->crearAsociacion('A');
        $asociacionB = $this->crearAsociacion('B');
        $alumno = $this->crearAlumno($asociacionA->id, '55550005', 'Fijo en A');

        Sanctum::actingAs($admin);

        // Payload intenta moverlo a B; asociacion_id fuera de fillable + no en
        // rules => ignorado. El alumno sigue en A (la ruta).
        $this->putJson("/api/v1/admin/asociaciones/{$asociacionA->id}/alumnos/{$alumno->id}", [
            'ci' => '55550005',
            'nombre_completo' => 'Fijo en A',
            'asociacion_id' => $asociacionB->id,
        ])->assertOk();

        $alumno->refresh();
        $this->assertEquals($asociacionA->id, $alumno->asociacion_id);
    }

    public function test_admin_desactiva_sin_borrar(): void
    {
        $admin = $this->crearAdmin();
        $asociacion = $this->crearAsociacion('A');
        $alumno = $this->crearAlumno($asociacion->id, '55550006', 'Para desactivar');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/asociaciones/{$asociacion->id}/alumnos/{$alumno->id}/estado", [
            'activo' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $this->assertDatabaseHas('asociacion_alumnos', ['id' => $alumno->id]);
        $this->assertDatabaseMissing('asociacion_alumnos', ['id' => $alumno->id, 'activo' => true]);
    }

    public function test_alumno_de_otra_asociacion_devuelve_404_en_update(): void
    {
        $admin = $this->crearAdmin();
        $asociacionA = $this->crearAsociacion('A');
        $asociacionB = $this->crearAsociacion('B');
        $alumnoB = $this->crearAlumno($asociacionB->id, '55550007', 'De B');

        Sanctum::actingAs($admin);

        // Ruta A, id de alumno de B => scoped findOrFail => 404 sin leakage.
        $this->putJson("/api/v1/admin/asociaciones/{$asociacionA->id}/alumnos/{$alumnoB->id}", [
            'ci' => '55550007',
            'nombre_completo' => 'Robado',
        ])->assertNotFound();

        $alumnoB->refresh();
        $this->assertEquals('De B', $alumnoB->nombre_completo);
    }

    public function test_listar_gestion_devuelve_todos_incl_inactivos_con_fecha(): void
    {
        $admin = $this->crearAdmin();
        $asociacion = $this->crearAsociacion('A');
        $activo = $this->crearAlumno($asociacion->id, '55550008', 'Activo', '2013-03-03');
        $inactivo = $this->crearAlumno($asociacion->id, '55550009', 'Inactivo', '2010-07-07');
        $inactivo->activo = false;
        $inactivo->save();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/admin/asociaciones/{$asociacion->id}/alumnos/gestion")
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertNotNull($data[0]['fecha_nacimiento']);
        $this->assertArrayHasKey('activo', $data[0]);
        $this->assertTrue(collect($data)->contains(fn ($a) => $a['id'] === $activo->id && $a['activo'] === true));
        $this->assertTrue(collect($data)->contains(fn ($a) => $a['id'] === $inactivo->id && $a['activo'] === false));
    }

    public function test_payload_con_asociacion_id_inyectado_es_ignorado_en_store(): void
    {
        $admin = $this->crearAdmin();
        $asociacionA = $this->crearAsociacion('A');
        $asociacionB = $this->crearAsociacion('B');

        Sanctum::actingAs($admin);

        // Intenta crear en B mandando asociacion_id=B, pero la ruta es A.
        $this->postJson("/api/v1/admin/asociaciones/{$asociacionA->id}/alumnos", [
            'ci' => '55550010',
            'nombre_completo' => 'Inyectado',
            'asociacion_id' => $asociacionB->id,
        ])->assertCreated();

        $this->assertDatabaseHas('asociacion_alumnos', [
            'ci' => '55550010',
            'asociacion_id' => $asociacionA->id,
        ]);
        $this->assertDatabaseMissing('asociacion_alumnos', [
            'ci' => '55550010',
            'asociacion_id' => $asociacionB->id,
        ]);
    }

    public function test_funcionario_control_no_puede_gestionar_alumnos(): void
    {
        $control = $this->crearFuncionarioRol('funcionario_control');
        $asociacion = $this->crearAsociacion('A');

        Sanctum::actingAs($control);

        $this->postJson("/api/v1/admin/asociaciones/{$asociacion->id}/alumnos", [
            'ci' => '55550011',
            'nombre_completo' => 'No debe entrar',
        ])->assertForbidden();
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function crearAdmin(): Funcionario
    {
        return $this->crearFuncionarioRol('admin_parametricas');
    }

    private function crearFuncionarioRol(string $rolNombre): Funcionario
    {
        $rol = Rol::query()->firstOrCreate(
            ['nombre' => $rolNombre],
            ['descripcion' => "Rol de prueba: {$rolNombre}", 'permisos' => []]
        );

        return Funcionario::create([
            'nombre_completo' => 'Func '.$rolNombre,
            'ci' => (string) random_int(100000, 999999),
            'usuario' => $rolNombre.'-'.Str::random(6),
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
            'mamore_id' => random_int(100000, 999999),
        ]);
    }

    private function crearAsociacion(string $sufijo): Asociacion
    {
        return Asociacion::create([
            'nit' => 'NIT-'.$sufijo.Str::upper(Str::random(4)),
            'nombre' => 'Asoc '.$sufijo,
            'tipo' => 'departamental',
            'convenio_numero' => 'CONV-'.$sufijo,
            'convenio_desde' => '2026-01-01',
            'convenio_hasta' => '2030-12-31',
            'responsable_nombre' => 'Resp '.$sufijo,
            'responsable_ci' => '8888'.random_int(1000, 9999),
            'responsable_telefono' => '70000000',
            'activo' => true,
        ]);
    }

    private function crearAlumno(string $aid, string $ci, string $nombre, ?string $nacimiento = '2012-01-01'): AsociacionAlumno
    {
        $a = new AsociacionAlumno();
        $a->asociacion_id = $aid;
        $a->ci = $ci;
        $a->nombre_completo = $nombre;
        $a->fecha_nacimiento = $nacimiento;
        $a->save();

        return $a;
    }
}

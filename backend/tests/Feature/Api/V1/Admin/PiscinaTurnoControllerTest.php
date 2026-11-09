<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\PiscinaTurno;
use App\Models\PiscinaTurnoPerfil;
use App\Models\Rol;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use App\Services\Piscina\PiscinaTarifaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PiscinaTurnoControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tarifaOriginalId;
    private string $tarifaResueltaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tarifaOriginalId = (string) Str::uuid();
        $this->tarifaResueltaId = (string) Str::uuid();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    private function admin(): Funcionario
    {
        $rol = Rol::forceCreate([
            'id' => (string) Str::uuid(),
            'nombre' => 'admin_parametricas',
            'descripcion' => 'Admin de prueba',
            'permisos' => ['*'],
        ]);

        return Funcionario::create([
            'nombre_completo' => 'Admin Test',
            'ci' => '12345678',
            'usuario' => 'admin_test',
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);
    }

    private function campoPiscina(): CampoDeportivo
    {
        $tipo = TipoCampo::forceCreate([
            'id' => (string) Str::uuid(),
            'nombre' => 'Piscina',
            'descripcion' => 'Piscina olímpica',
            'estado' => 'activo',
        ]);

        return CampoDeportivo::forceCreate([
            'id' => (string) Str::uuid(),
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'PISC-'.strtoupper(Str::random(6)),
            'nombre' => 'Piscina de prueba',
            'direccion' => 'Av. Test 123',
            'estado' => 'activo',
            'modalidad_reserva' => 'turno_por_perfil',
            'latitud' => -14.8000,
            'longitud' => -64.9000,
            'hora_inicio_noche' => '18:00:00',
        ]);
    }

    private function turno(CampoDeportivo $campo, array $perfiles = []): PiscinaTurno
    {
        $turno = PiscinaTurno::forceCreate([
            'id' => (string) Str::uuid(),
            'campo_id' => $campo->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'cupo_total' => 10,
            'estado' => 'activo',
            'observacion' => null,
        ]);

        foreach ($perfiles as $perfil) {
            PiscinaTurnoPerfil::forceCreate([
                'id' => (string) Str::uuid(),
                'turno_id' => $turno->id,
                'perfil' => $perfil['perfil'],
                'cupo' => $perfil['cupo'],
                'reservado' => $perfil['reservado'] ?? 0,
                'tarifa_sireb_id' => $perfil['tarifa_sireb_id'] ?? $this->tarifaOriginalId,
                'precio_unitario' => $perfil['precio_unitario'] ?? 40,
                'estado' => 'activo',
            ]);
        }

        return $turno->load('perfiles');
    }

    private function fakeResolver(): void
    {
        $tarifa = new TarifaCampo();
        $tarifa->id = $this->tarifaResueltaId;
        $tarifa->precio_por_hora = 50;
        $tarifa->tarifa_sireb_id = $this->tarifaResueltaId;
        $tarifa->servicio_sireb_id = (string) Str::uuid();
        $tarifa->etiqueta_sireb = 'PISCINA TEST';

        $resolver = \Mockery::mock(PiscinaTarifaResolver::class);
        $resolver->shouldReceive('resolver')->andReturn($tarifa);

        $this->app->instance(PiscinaTarifaResolver::class, $resolver);
    }

    public function test_admin_puede_editar_estado_y_observacion_sin_tocar_perfiles(): void
    {
        Sanctum::actingAs($this->admin());

        $campo = $this->campoPiscina();
        $turno = $this->turno($campo, [
            ['perfil' => 'mayor_12', 'cupo' => 5, 'reservado' => 2],
        ]);

        $response = $this->patchJson("/api/v1/admin/piscina/turnos/{$turno->id}", [
            'estado' => 'inactivo',
            'observacion' => 'Mantenimiento programado',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.estado', 'inactivo')
            ->assertJsonPath('data.observacion', 'Mantenimiento programado');

        $this->assertDatabaseHas('piscina_turnos', [
            'id' => $turno->id,
            'estado' => 'inactivo',
            'observacion' => 'Mantenimiento programado',
        ]);

        $this->assertDatabaseHas('piscina_turno_perfiles', [
            'turno_id' => $turno->id,
            'perfil' => 'mayor_12',
            'cupo' => 5,
            'reservado' => 2,
        ]);
    }

    public function test_update_no_permite_dejar_perfiles_vacio(): void
    {
        Sanctum::actingAs($this->admin());

        $campo = $this->campoPiscina();
        $turno = $this->turno($campo, [
            ['perfil' => 'mayor_12', 'cupo' => 5, 'reservado' => 0],
        ]);

        $response = $this->patchJson("/api/v1/admin/piscina/turnos/{$turno->id}", [
            'perfiles' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('perfiles');

        $this->assertDatabaseHas('piscina_turno_perfiles', [
            'turno_id' => $turno->id,
            'perfil' => 'mayor_12',
        ]);
    }

    public function test_update_no_baja_cupo_por_debajo_de_lo_reservado(): void
    {
        Sanctum::actingAs($this->admin());
        $this->fakeResolver();

        $campo = $this->campoPiscina();
        $turno = $this->turno($campo, [
            ['perfil' => 'mayor_12', 'cupo' => 5, 'reservado' => 2],
        ]);

        $response = $this->patchJson("/api/v1/admin/piscina/turnos/{$turno->id}", [
            'perfiles' => [
                ['perfil' => 'mayor_12', 'cupo' => 1],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertStringContainsString(
            'no puede ser menor a 2',
            (string) $response->json('message')
        );

        $this->assertDatabaseHas('piscina_turno_perfiles', [
            'turno_id' => $turno->id,
            'perfil' => 'mayor_12',
            'cupo' => 5,
            'reservado' => 2,
        ]);
    }

    public function test_update_no_quita_perfil_con_reservas(): void
    {
        Sanctum::actingAs($this->admin());
        $this->fakeResolver();

        $campo = $this->campoPiscina();
        $turno = $this->turno($campo, [
            ['perfil' => 'mayor_12', 'cupo' => 5, 'reservado' => 2],
        ]);

        $response = $this->patchJson("/api/v1/admin/piscina/turnos/{$turno->id}", [
            'perfiles' => [
                ['perfil' => 'menor_12', 'cupo' => 4],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertStringContainsString(
            'No se puede quitar el perfil mayor_12',
            (string) $response->json('message')
        );

        $this->assertDatabaseHas('piscina_turno_perfiles', [
            'turno_id' => $turno->id,
            'perfil' => 'mayor_12',
            'reservado' => 2,
        ]);
    }

    public function test_observacion_null_si_limpia_la_observacion(): void
    {
        Sanctum::actingAs($this->admin());

        $campo = $this->campoPiscina();
        $turno = $this->turno($campo, [
            ['perfil' => 'mayor_12', 'cupo' => 5, 'reservado' => 0],
        ]);

        $turno->update(['observacion' => 'Nota vieja']);

        $response = $this->patchJson("/api/v1/admin/piscina/turnos/{$turno->id}", [
            'observacion' => null,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.observacion', null);

        $this->assertDatabaseHas('piscina_turnos', [
            'id' => $turno->id,
            'observacion' => null,
        ]);
    }

    public function test_update_valido_preserva_reservado_y_actualiza_cupo(): void
    {
        Sanctum::actingAs($this->admin());
        $this->fakeResolver();

        $campo = $this->campoPiscina();
        $turno = $this->turno($campo, [
            ['perfil' => 'mayor_12', 'cupo' => 5, 'reservado' => 2],
        ]);

        $response = $this->patchJson("/api/v1/admin/piscina/turnos/{$turno->id}", [
            'perfiles' => [
                ['perfil' => 'mayor_12', 'cupo' => 6],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.perfiles.0.cupo', 6)
            ->assertJsonPath('data.perfiles.0.reservado', 2)
            ->assertJsonPath('data.perfiles.0.tarifa_sireb_id', $this->tarifaResueltaId);

        $this->assertDatabaseHas('piscina_turno_perfiles', [
            'turno_id' => $turno->id,
            'perfil' => 'mayor_12',
            'cupo' => 6,
            'reservado' => 2,
            'tarifa_sireb_id' => $this->tarifaResueltaId,
            'precio_unitario' => 50,
        ]);
    }
}

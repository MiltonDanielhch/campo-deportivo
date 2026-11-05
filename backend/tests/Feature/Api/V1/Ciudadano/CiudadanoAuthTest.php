<?php

namespace Tests\Feature\Api\V1\Ciudadano;

use App\Models\Asociacion;
use App\Models\AsociacionMiembro;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Ciudadano;
use Laravel\Sanctum\PersonalAccessToken;

class CiudadanoAuthTest extends TestCase
{
    use RefreshDatabase;

    private function asociacionVigente(string $ciResponsable, string $hasta = '2030-12-31'): Asociacion
    {
        // create() (no forceFill estatico): los campos SI estan en $fillable,
        // probado por AsociacionController::store que hace create($r->validated()).
        return Asociacion::create([
            'nit' => 'NIT-' . uniqid(),
            'nombre' => 'Asociacion Test ' . uniqid(),
            'tipo' => 'departamental',
            'convenio_numero' => 'CONV-' . uniqid(),
            'convenio_desde' => '2026-01-01',
            'convenio_hasta' => $hasta,
            'responsable_nombre' => 'Responsable Test',
            'responsable_ci' => $ciResponsable,
            'responsable_telefono' => '70000000',
            'activo' => true,
        ]);
    }

    private function payloadRegistro(string $ci, string $password = 'secreto123'): array
    {
        return [
            'ci' => $ci,
            'nombre_completo' => 'Juan Perez',
            'telefono' => '70000000',
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    public function test_registro_ciudadano_comun_no_queda_como_asociacion(): void
    {
        $response = $this->postJson('/api/v1/ciudadanos/auth/registro', $this->payloadRegistro('11111111'));

        $response->assertStatus(201)
            ->assertJsonPath('data.ciudadano.es_asociacion', false)
            ->assertJsonPath('data.ciudadano.asociacion', null);

        $this->assertDatabaseHas('ciudadanos', ['ci' => '11111111', 'es_asociacion' => false]);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_registro_responsable_de_asociacion_activa_se_vincula(): void
    {
        $asociacion = $this->asociacionVigente('22222222');

        $response = $this->postJson('/api/v1/ciudadanos/auth/registro', $this->payloadRegistro('22222222'));

        $response->assertStatus(201)
            ->assertJsonPath('data.ciudadano.es_asociacion', true)
            ->assertJsonPath('data.ciudadano.asociacion.id', $asociacion->id);

        $this->assertDatabaseHas('ciudadanos', [
            'ci' => '22222222',
            'es_asociacion' => true,
            'asociacion_id' => $asociacion->id,
        ]);
    }

    public function test_registro_responsable_de_asociacion_vencida_no_se_vincula(): void
    {
        $this->asociacionVigente('33333333', hasta: '2020-01-01');

        $response = $this->postJson('/api/v1/ciudadanos/auth/registro', $this->payloadRegistro('33333333'));

        $response->assertStatus(201)
            ->assertJsonPath('data.ciudadano.es_asociacion', false)
            ->assertJsonPath('data.ciudadano.asociacion', null);
    }

    public function test_registro_ignora_es_asociacion_inyectado_en_payload(): void
    {
        $payload = $this->payloadRegistro('44444444') + [
            'es_asociacion' => true,
            'asociacion_id' => 'cualquier-uuid',
        ];

        $response = $this->postJson('/api/v1/ciudadanos/auth/registro', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.ciudadano.es_asociacion', false);
    }

    public function test_login_contrasena_invalida_devuelve_401(): void
    {
        $this->postJson('/api/v1/ciudadanos/auth/registro', $this->payloadRegistro('55555555'))
            ->assertStatus(201);

        $this->postJson('/api/v1/ciudadanos/auth/login', [
            'ci' => '55555555',
            'password' => 'mala',
        ])->assertStatus(401)->assertJsonPath('error', 'CREDENCIALES_INVALIDAS');
    }

    public function test_me_devuelve_asociacion_cuando_vinculado(): void
    {
        $asociacion = $this->asociacionVigente('66666666');
        $token = $this->postJson('/api/v1/ciudadanos/auth/registro', $this->payloadRegistro('66666666'))
            ->json('data.token');

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/ciudadanos/me')
            ->assertStatus(200)
            ->assertJsonPath('data.es_asociacion', true)
            ->assertJsonPath('data.asociacion.id', $asociacion->id);
    }

    public function test_endpoint_protegido_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/ciudadanos/me')->assertStatus(401);
    }

    public function test_logout_invalida_el_token_actual(): void
    {
        $token = $this->postJson('/api/v1/ciudadanos/auth/registro', $this->payloadRegistro('77777777'))
            ->json('data.token');

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/ciudadanos/auth/logout')
            ->assertStatus(200);

        // El guard de Sanctum cachea el usuario resuelto en $this->user dentro
        // del MISMO contenedor. En produccion cada request es un proceso nuevo
        // y el token borrado SI da 401; en el test el contenedor se reusa entre
        // requests y el guard devuelve el usuario cacheado sin re-mirar el
        // token. forgetGuards() fuerza la re-resolucion -> replica produccion.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/ciudadanos/me')
            ->assertStatus(401);
    }

    public function test_endpoint_publico_sigue_accesible_sin_auth(): void
    {
        $this->getJson('/api/v1/public/campos')->assertStatus(200);
    }

    public function test_miembro_unico_por_asociacion_y_ci(): void
    {
        $asociacion = $this->asociacionVigente('88888888');

        AsociacionMiembro::create([
            'asociacion_id' => $asociacion->id,
            'ci' => '99999999',
            'nombre_completo' => 'Miembro Uno',
            'rol' => 'profe',
            'activo' => true,
        ]);

        try {
            AsociacionMiembro::create([
                'asociacion_id' => $asociacion->id,
                'ci' => '99999999',
                'nombre_completo' => 'Miembro Dos',
                'rol' => 'tesorero',
                'activo' => true,
            ]);
            $this->fail('El unique (asociacion_id, ci) debio lanzar 23505');
        } catch (QueryException $e) {
            $this->assertSame('23505', $e->getCode());
        }
    }
}

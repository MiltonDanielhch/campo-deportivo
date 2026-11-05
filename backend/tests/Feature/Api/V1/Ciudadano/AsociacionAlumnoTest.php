<?php

namespace Tests\Feature\Api\V1\Ciudadano;

use App\Models\Asociacion;
use App\Models\AsociacionAlumno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsociacionAlumnoTest extends TestCase
{
    use RefreshDatabase;

    private function asociacionVigente(string $ciResponsable): Asociacion
    {
        return Asociacion::create([
            'nit' => 'NIT-' . uniqid(),
            'nombre' => 'Asociacion Test ' . uniqid(),
            'tipo' => 'departamental',
            'convenio_numero' => 'CONV-' . uniqid(),
            'convenio_desde' => '2026-01-01',
            'convenio_hasta' => '2030-12-31',
            'responsable_nombre' => 'Responsable Test',
            'responsable_ci' => $ciResponsable,
            'responsable_telefono' => '70000000',
            'activo' => true,
        ]);
    }

    /** Registra al responsable como ciudadano (queda es_asociacion) y devuelve [token, asociacion_id]. */
    private function presidenteToken(string $ciResponsable): array
    {
        $asociacion = $this->asociacionVigente($ciResponsable);
        $res = $this->postJson('/api/v1/ciudadanos/auth/registro', [
            'ci' => $ciResponsable,
            'nombre_completo' => 'Presidente Test',
            'telefono' => '70000000',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])->assertStatus(201);

        return [$res->json('data.token'), $asociacion->id];
    }

    private function alumnoPayload(string $ci = '44445555', string $nombre = 'Alumno Uno'): array
    {
        return [
            'ci' => $ci,
            'nombre_completo' => $nombre,
            'fecha_nacimiento' => '2010-05-05',
        ];
    }

    public function test_index_lista_solo_alumnos_de_mi_asociacion(): void
    {
        [$token, $aid] = $this->presidenteToken('22222222');

        // mio (via HTTP)
        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/ciudadanos/alumnos', $this->alumnoPayload('44440001'))
            ->assertStatus(201);

        // de OTRA asociacion (fixture directo, forceFill porque asociacion_id no es fillable)
        $otra = $this->asociacionVigente('99999999');
        (new AsociacionAlumno())->forceFill([
            'asociacion_id' => $otra->id,
            'ci' => '44449999',
            'nombre_completo' => 'Ajeno',
        ])->save();

        $list = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/ciudadanos/alumnos')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $list);
        $this->assertSame('44440001', $list[0]['ci']);
    }

    // Regresion de seguridad: aunque el payload mande otra asociacion_id, se
    // guarda la del token (espejo del test de inyeccion de es_asociacion).
    public function test_store_ignora_asociacion_id_del_payload(): void
    {
        [$token, $aid] = $this->presidenteToken('22222222');
        $otra = $this->asociacionVigente('88888888');

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/ciudadanos/alumnos', $this->alumnoPayload('44441111') + [
                'asociacion_id' => $otra->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('asociacion_alumnos', [
            'ci' => '44441111',
            'asociacion_id' => $aid, // la del token, NO la del payload
        ]);
        $this->assertDatabaseMissing('asociacion_alumnos', [
            'ci' => '44441111',
            'asociacion_id' => $otra->id,
        ]);
    }

    public function test_store_bloqueado_para_ciudadano_comun(): void
    {
        // CI que NO es responsable de ninguna asociacion -> es_asociacion=false.
        $token = $this->postJson('/api/v1/ciudadanos/auth/registro', [
            'ci' => '11111111',
            'nombre_completo' => 'Vecino Suelto',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])->assertStatus(201)->json('data.token');

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/ciudadanos/alumnos', $this->alumnoPayload())
            ->assertStatus(403)
            ->assertJsonPath('error', 'NO_ES_ASOCIACION');
    }

    public function test_store_ci_duplicado_en_la_misma_asociacion_devuelve_422(): void
    {
        [$token] = $this->presidenteToken('22222222');

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/ciudadanos/alumnos', $this->alumnoPayload('44442222'))
            ->assertStatus(201);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/ciudadanos/alumnos', $this->alumnoPayload('44442222'))
            ->assertStatus(422);
    }

    public function test_update_no_mueve_a_otra_asociacion(): void
    {
        [$token, $aid] = $this->presidenteToken('22222222');
        $otra = $this->asociacionVigente('77777777');

        $id = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/ciudadanos/alumnos', $this->alumnoPayload('44443333'))
            ->assertStatus(201)->json('data.id');

        $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/v1/ciudadanos/alumnos/{$id}", $this->alumnoPayload('44443333', 'Renombrado') + [
                'asociacion_id' => $otra->id,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('asociacion_alumnos', [
            'id' => $id,
            'asociacion_id' => $aid,
            'nombre_completo' => 'Renombrado',
        ]);
    }

    // Sin destroy: desactivar conserva la fila (y por tanto la puente historica).
    public function test_cambiar_estado_desactiva_sin_borrar(): void
    {
        [$token] = $this->presidenteToken('22222222');

        $id = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/ciudadanos/alumnos', $this->alumnoPayload('44446666'))
            ->assertStatus(201)->json('data.id');

        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/v1/ciudadanos/alumnos/{$id}/estado", ['activo' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.activo', false);

        $this->assertDatabaseHas('asociacion_alumnos', ['id' => $id, 'activo' => false]);
    }

    public function test_alumno_de_otra_asociacion_devuelve_404_en_update(): void
    {
        [$token] = $this->presidenteToken('22222222');
        $otra = $this->asociacionVigente('66666666');

        $ajeno = (new AsociacionAlumno())->forceFill([
            'asociacion_id' => $otra->id,
            'ci' => '44447777',
            'nombre_completo' => 'Ajeno',
        ]);
        $ajeno->save();

        $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/v1/ciudadanos/alumnos/{$ajeno->id}", $this->alumnoPayload('44447777'))
            ->assertStatus(404);
    }
}

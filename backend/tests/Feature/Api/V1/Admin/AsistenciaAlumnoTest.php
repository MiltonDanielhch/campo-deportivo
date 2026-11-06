<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\EstadoSolicitudReserva;
use App\Models\AsignacionFuncionario;
use App\Models\Asociacion;
use App\Models\AsociacionAlumno;
use App\Models\Auditoria;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\SolicitudDetalleAlumno;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaDetalle;
use App\Models\TipoCampo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AsistenciaAlumnoTest extends TestCase
{
    use RefreshDatabase;

    public function test_funcionario_asignado_marca_alumno_dentro_de_ventana(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00'));

        $control = $this->crearFuncionarioControl();
        [$solicitud, $reserva, $campo, $bridge] = $this->crearReservaConfirmadaConAlumnos();
        $this->asignarCampo($control, $campo);

        Sanctum::actingAs($control);

        $response = $this->postJson("/api/v1/admin/solicitud-detalle-alumno/{$bridge->id}/asistencia", [
            'marcar' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $bridge->id)
            ->assertJsonPath('data.alumno_id', $bridge->alumno_id)
            ->assertJsonPath('data.asistencia_marca_por', $control->id);

        $this->assertNotNull($response->json('data.asistencia_marca_en'));

        $bridge->refresh();
        $this->assertNotNull($bridge->asistencia_marca_en);
        $this->assertEquals($control->id, $bridge->asistencia_marca_por);

        $this->assertDatabaseHas('auditoria', [
            'tabla' => 'solicitud_detalle_alumno',
            'registro_id' => $bridge->id,
            'accion' => 'marcar_asistencia_alumno',
            'usuario_id' => $control->id,
        ]);
    }

    public function test_funcionario_no_asignado_recibe_403(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00'));

        $control = $this->crearFuncionarioControl();
        [, , , $bridge] = $this->crearReservaConfirmadaConAlumnos();

        Sanctum::actingAs($control);

        $this->postJson("/api/v1/admin/solicitud-detalle-alumno/{$bridge->id}/asistencia", [
            'marcar' => true,
        ])->assertForbidden();

        $bridge->refresh();
        $this->assertNull($bridge->asistencia_marca_en);
    }

    public function test_funcionario_asignado_fuera_de_ventana_recibe_422(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 16:30:00'));

        $control = $this->crearFuncionarioControl();
        [, , $campo, $bridge] = $this->crearReservaConfirmadaConAlumnos();
        $this->asignarCampo($control, $campo);

        Sanctum::actingAs($control);

        $this->postJson("/api/v1/admin/solicitud-detalle-alumno/{$bridge->id}/asistencia", [
            'marcar' => true,
        ])->assertStatus(422);

        $bridge->refresh();
        $this->assertNull($bridge->asistencia_marca_en);
    }

    public function test_admin_puede_marcar_alumno_fuera_de_ventana(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 18:30:00'));

        $admin = $this->crearFuncionarioAdmin();
        [, , , $bridge] = $this->crearReservaConfirmadaConAlumnos();

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/admin/solicitud-detalle-alumno/{$bridge->id}/asistencia", [
            'marcar' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.asistencia_marca_por', $admin->id);

        $bridge->refresh();
        $this->assertNotNull($bridge->asistencia_marca_en);
    }

    public function test_desmarcar_deja_asistencia_nula_y_audita_desmarcar(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00'));

        $admin = $this->crearFuncionarioAdmin();
        [, , , $bridge] = $this->crearReservaConfirmadaConAlumnos();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/solicitud-detalle-alumno/{$bridge->id}/asistencia", [
            'marcar' => true,
        ])->assertOk();

        $this->travelTo(Carbon::parse('2026-10-03 18:40:00'));

        $this->postJson("/api/v1/admin/solicitud-detalle-alumno/{$bridge->id}/asistencia", [
            'marcar' => false,
        ])->assertOk();

        $bridge->refresh();
        $this->assertNull($bridge->asistencia_marca_en);
        $this->assertNull($bridge->asistencia_marca_por);

        $this->assertDatabaseHas('auditoria', [
            'tabla' => 'solicitud_detalle_alumno',
            'registro_id' => $bridge->id,
            'accion' => 'desmarcar_asistencia_alumno',
            'usuario_id' => $admin->id,
        ]);
    }

    public function test_marcar_dos_veces_no_duplica_timestamp_pero_audita_ambas(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00'));

        $admin = $this->crearFuncionarioAdmin();
        [, , , $bridge] = $this->crearReservaConfirmadaConAlumnos();

        Sanctum::actingAs($admin);

        $primera = $this->postJson("/api/v1/admin/solicitud-detalle-alumno/{$bridge->id}/asistencia", [
            'marcar' => true,
        ])->assertOk()->json('data.asistencia_marca_en');

        $this->travelTo(Carbon::parse('2026-10-03 18:40:00'));

        $segunda = $this->postJson("/api/v1/admin/solicitud-detalle-alumno/{$bridge->id}/asistencia", [
            'marcar' => true,
        ])->assertOk()->json('data.asistencia_marca_en');

        $this->assertEquals($primera, $segunda);

        $marcaciones = Auditoria::where('tabla', 'solicitud_detalle_alumno')
            ->where('registro_id', $bridge->id)
            ->where('accion', 'marcar_asistencia_alumno')
            ->count();

        $this->assertSame(2, $marcaciones);
    }

    public function test_no_se_puede_marcar_alumno_de_solicitud_expirada(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00'));

        $admin = $this->crearFuncionarioAdmin();
        [, , , $bridge] = $this->crearReservaConfirmadaConAlumnos(
            estado: EstadoSolicitudReserva::Expirada
        );

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/solicitud-detalle-alumno/{$bridge->id}/asistencia", [
            'marcar' => true,
        ])->assertStatus(422);

        $bridge->refresh();
        $this->assertNull($bridge->asistencia_marca_en);
    }

    public function test_bridge_inexistente_devuelve_404(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/solicitud-detalle-alumno/'.Str::uuid()->toString().'/asistencia', [
            'marcar' => true,
        ])->assertNotFound();
    }

    public function test_payload_invalido_devuelve_422(): void
    {
        $admin = $this->crearFuncionarioAdmin();
        [, , , $bridge] = $this->crearReservaConfirmadaConAlumnos();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/solicitud-detalle-alumno/{$bridge->id}/asistencia", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('marcar');
    }

    public function test_detalle_admin_incluye_alumnos_con_asistencia(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00'));

        $admin = $this->crearFuncionarioAdmin();
        [$solicitud, , , $bridge] = $this->crearReservaConfirmadaConAlumnos();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/solicitud-detalle-alumno/{$bridge->id}/asistencia", [
            'marcar' => true,
        ])->assertOk();

        $this->getJson("/api/v1/admin/solicitudes-reserva/{$solicitud->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'solicitud' => [
                        'detalles' => [
                            '*' => [
                                'alumnos' => [
                                    '*' => [
                                        'id',
                                        'alumno_id',
                                        'nombre_completo',
                                        'ci',
                                        'asistencia_marca_en',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertNotNull(
            $this->getJson("/api/v1/admin/solicitudes-reserva/{$solicitud->id}")
                ->json('data.solicitud.detalles.0.alumnos.0.asistencia_marca_en')
        );
    }

    // ─────────────────────────────────────────────
    // Helpers (espejo de AsistenciaReservaTest + bridge de ConvenioAlumnosVentanillaTest)
    // ─────────────────────────────────────────────

    private function crearFuncionarioAdmin(): Funcionario
    {
        $rol = $this->crearRol('admin_reservas');

        return Funcionario::create([
            'nombre_completo' => 'Admin Asistencia Alumno',
            'ci' => '111111',
            'usuario' => 'admin-asal-'.Str::random(6),
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
            'mamore_id' => random_int(100000, 999999),
        ]);
    }

    private function crearFuncionarioControl(): Funcionario
    {
        $rol = $this->crearRol('funcionario_control');

        return Funcionario::create([
            'nombre_completo' => 'Control Asistencia Alumno',
            'ci' => '222222',
            'usuario' => 'control-asal-'.Str::random(6),
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
            'mamore_id' => random_int(100000, 999999),
        ]);
    }

    private function crearRol(string $nombre): Rol
    {
        return Rol::query()->firstOrCreate(
            ['nombre' => $nombre],
            ['descripcion' => "Rol de prueba: {$nombre}", 'permisos' => []]
        );
    }

    private function crearCampo(): CampoDeportivo
    {
        $tipo = TipoCampo::query()->firstOrCreate(
            ['nombre' => 'Futsal'],
            ['descripcion' => 'Tipo de prueba', 'estado' => 'activo']
        );

        return CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CAMPO-'.Str::upper(Str::random(6)),
            'nombre' => 'Cancha de asistencia alumno',
            'direccion' => 'Dirección de prueba',
            'latitud' => -14.83333333,
            'longitud' => -64.90000000,
            'estado' => 'activo',
            'hora_inicio_noche' => '18:00:00',
        ]);
    }

    private function asignarCampo(Funcionario $funcionario, CampoDeportivo $campo): void
    {
        AsignacionFuncionario::create([
            'funcionario_id' => $funcionario->id,
            'campo_id' => $campo->id,
            'asignado_en' => now(),
        ]);
    }

    private function crearAsociacionConAlumnos(): array
    {
        $asociacion = Asociacion::create([
            'nit' => 'NIT-'.Str::upper(Str::random(6)),
            'nombre' => 'Asoc Asistencia',
            'tipo' => 'departamental',
            'convenio_numero' => 'CONV-'.Str::upper(Str::random(6)),
            'convenio_desde' => '2026-01-01',
            'convenio_hasta' => '2030-12-31',
            'responsable_nombre' => 'Resp',
            'responsable_ci' => '9999999',
            'responsable_telefono' => '70000000',
            'activo' => true,
        ]);

        $alumnos = [];
        for ($i = 0; $i < 2; $i++) {
            $al = new AsociacionAlumno();
            $al->asociacion_id = $asociacion->id;
            $al->ci = (string) (44440000 + $i);
            $al->nombre_completo = 'Alumno '.$i;
            $al->save();
            $alumnos[] = $al;
        }

        return [$asociacion, $alumnos];
    }

    /**
     * @return array{0: SolicitudReserva, 1: Reserva, 2: CampoDeportivo, 3: SolicitudDetalleAlumno}
     */
    private function crearReservaConfirmadaConAlumnos(
        string $fecha = '2026-10-03',
        string $horaInicio = '18:00:00',
        string $horaFin = '20:00:00',
        EstadoSolicitudReserva $estado = EstadoSolicitudReserva::Confirmada,
    ): array {
        $campo = $this->crearCampo();
        [, $alumnos] = $this->crearAsociacionConAlumnos();

        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'monto_total' => 100,
            'monto_confirmado' => $estado === EstadoSolicitudReserva::Confirmada ? 100 : null,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '9999999',
            'estado' => $estado,
            'expira_en' => now()->addMinutes(15),
        ]);

        $detalle = SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'tarifa_aplicada' => 100,
        ]);

        $reserva = Reserva::create([
            'solicitud_reserva_id' => $solicitud->id,
            'solicitud_reserva_detalle_id' => $detalle->id,
            'codigo_reserva' => 'RSV-'.Str::upper(Str::random(5)),
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'monto_pagado' => 100,
            'confirmado_en' => now(),
        ]);

        // Bridge: los 2 alumnos asignados a esta franja. Devuelvo el primero
        // pa' los tests de marcar; el resource expone los 2.
        $bridges = [];
        foreach ($alumnos as $al) {
            $bridges[] = SolicitudDetalleAlumno::create([
                'detalle_id' => $detalle->id,
                'alumno_id' => $al->id,
            ]);
        }

        return [$solicitud, $reserva, $campo, $bridges[0]];
    }
}

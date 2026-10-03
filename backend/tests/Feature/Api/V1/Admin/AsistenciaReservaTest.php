<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\EstadoSolicitudReserva;
use App\Models\AsignacionFuncionario;
use App\Models\Auditoria;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaDetalle;
use App\Models\TipoCampo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AsistenciaReservaTest extends TestCase
{
    use RefreshDatabase;

    public function test_funcionario_asignado_marca_dentro_de_ventana(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00'));

        $control = $this->crearFuncionarioControl();
        [$solicitud, $reserva, $campo] = $this->crearReservaConfirmada();
        $this->asignarCampo($control, $campo);

        Sanctum::actingAs($control);

        $response = $this->postJson("/api/v1/admin/reservas/{$reserva->id}/asistencia", [
            'marcar' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $reserva->id)
            ->assertJsonPath('data.asistencia_marcada_por', $control->id);

        $this->assertNotNull($response->json('data.asistencia_marcada_en'));

        $reserva->refresh();

        $this->assertNotNull($reserva->asistencia_marcada_en);
        $this->assertEquals($control->id, $reserva->asistencia_marcada_por);

        $this->assertDatabaseHas('auditoria', [
            'tabla' => 'reservas',
            'registro_id' => $reserva->id,
            'accion' => 'marcar_asistencia',
            'usuario_id' => $control->id,
        ]);
    }

    public function test_funcionario_no_asignado_recibe_403(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00'));

        $control = $this->crearFuncionarioControl();
        [, $reserva] = $this->crearReservaConfirmada();

        Sanctum::actingAs($control);

        $this->postJson("/api/v1/admin/reservas/{$reserva->id}/asistencia", [
            'marcar' => true,
        ])->assertForbidden();

        $reserva->refresh();

        $this->assertNull($reserva->asistencia_marcada_en);
    }

    public function test_funcionario_asignado_fuera_de_ventana_recibe_422(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 16:30:00'));

        $control = $this->crearFuncionarioControl();
        [, $reserva, $campo] = $this->crearReservaConfirmada();
        $this->asignarCampo($control, $campo);

        Sanctum::actingAs($control);

        $this->postJson("/api/v1/admin/reservas/{$reserva->id}/asistencia", [
            'marcar' => true,
        ])->assertStatus(422);

        $reserva->refresh();

        $this->assertNull($reserva->asistencia_marcada_en);
    }

    public function test_admin_puede_marcar_fuera_de_ventana(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 18:30:00'));

        $admin = $this->crearFuncionarioAdmin();
        [, $reserva] = $this->crearReservaConfirmada();

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/admin/reservas/{$reserva->id}/asistencia", [
            'marcar' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.asistencia_marcada_por', $admin->id);

        $reserva->refresh();

        $this->assertNotNull($reserva->asistencia_marcada_en);
    }

    public function test_desmarcar_deja_asistencia_nula_y_audita_desmarcar(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00'));

        $admin = $this->crearFuncionarioAdmin();
        [, $reserva] = $this->crearReservaConfirmada();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/reservas/{$reserva->id}/asistencia", [
            'marcar' => true,
        ])->assertOk();

        $this->travelTo(Carbon::parse('2026-10-03 18:40:00'));

        $this->postJson("/api/v1/admin/reservas/{$reserva->id}/asistencia", [
            'marcar' => false,
        ])->assertOk();

        $reserva->refresh();

        $this->assertNull($reserva->asistencia_marcada_en);
        $this->assertNull($reserva->asistencia_marcada_por);

        $this->assertDatabaseHas('auditoria', [
            'tabla' => 'reservas',
            'registro_id' => $reserva->id,
            'accion' => 'desmarcar_asistencia',
            'usuario_id' => $admin->id,
        ]);
    }

    public function test_marcar_dos_veces_no_duplica_timestamp_pero_audita_ambas_acciones(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00'));

        $admin = $this->crearFuncionarioAdmin();
        [, $reserva] = $this->crearReservaConfirmada();

        Sanctum::actingAs($admin);

        $primera = $this->postJson("/api/v1/admin/reservas/{$reserva->id}/asistencia", [
            'marcar' => true,
        ])->assertOk()->json('data.asistencia_marcada_en');

        $this->travelTo(Carbon::parse('2026-10-03 18:40:00'));

        $segunda = $this->postJson("/api/v1/admin/reservas/{$reserva->id}/asistencia", [
            'marcar' => true,
        ])->assertOk()->json('data.asistencia_marcada_en');

        $this->assertEquals($primera, $segunda);

        $marcaciones = Auditoria::where('tabla', 'reservas')
            ->where('registro_id', $reserva->id)
            ->where('accion', 'marcar_asistencia')
            ->count();

        $this->assertSame(2, $marcaciones);
    }

    public function test_no_se_puede_marcar_asistencia_de_solicitud_expirada(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00'));

        $admin = $this->crearFuncionarioAdmin();
        [, $reserva] = $this->crearReservaConfirmada(
            estado: EstadoSolicitudReserva::Expirada
        );

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/reservas/{$reserva->id}/asistencia", [
            'marcar' => true,
        ])->assertStatus(422);

        $reserva->refresh();

        $this->assertNull($reserva->asistencia_marcada_en);
    }

    public function test_reserva_inexistente_devuelve_404(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/reservas/'.Str::uuid()->toString().'/asistencia', [
            'marcar' => true,
        ])->assertNotFound();
    }

    public function test_payload_invalido_devuelve_422(): void
    {
        $admin = $this->crearFuncionarioAdmin();
        [, $reserva] = $this->crearReservaConfirmada();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/reservas/{$reserva->id}/asistencia", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('marcar');
    }

    public function test_detalle_admin_incluye_asistencia_marcada_en(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00'));

        $admin = $this->crearFuncionarioAdmin();
        [$solicitud, $reserva] = $this->crearReservaConfirmada();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/reservas/{$reserva->id}/asistencia", [
            'marcar' => true,
        ])->assertOk();

        $this->getJson("/api/v1/admin/solicitudes-reserva/{$solicitud->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.solicitud.detalles.0.reserva.id',
                $reserva->id
            )
            ->assertJsonStructure([
                'data' => [
                    'solicitud' => [
                        'detalles' => [
                            '*' => [
                                'reserva' => [
                                    'id',
                                    'codigo_reserva',
                                    'asistencia_marcada_en',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertNotNull(
            $this->getJson("/api/v1/admin/solicitudes-reserva/{$solicitud->id}")
                ->json('data.solicitud.detalles.0.reserva.asistencia_marcada_en')
        );
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function crearFuncionarioAdmin(): Funcionario
    {
        $rol = $this->crearRol('admin_reservas');

        return Funcionario::create([
            'nombre_completo' => 'Admin Asistencia',
            'ci' => '111111',
            'usuario' => 'admin-asistencia-'.Str::random(6),
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
            'nombre_completo' => 'Control Asistencia',
            'ci' => '222222',
            'usuario' => 'control-asistencia-'.Str::random(6),
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
            [
                'descripcion' => "Rol de prueba: {$nombre}",
                'permisos' => [],
            ]
        );
    }

        private function crearCampo(): CampoDeportivo
        {
            $tipo = TipoCampo::query()->firstOrCreate(
                ['nombre' => 'Futsal'],
                [
                    'descripcion' => 'Tipo de prueba',
                    'estado' => 'activo',
                ]
            );

        return CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CAMPO-'.Str::upper(Str::random(6)),
            'nombre' => 'Cancha de asistencia',
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

    /**
     * @return array{0: SolicitudReserva, 1: Reserva, 2: CampoDeportivo}
     */
        /**
     * @return array{0: SolicitudReserva, 1: Reserva, 2: CampoDeportivo}
     */
    /**
     * @return array{0: SolicitudReserva, 1: Reserva, 2: CampoDeportivo}
     */
    private function crearReservaConfirmada(
        string $fecha = '2026-10-03',
        string $horaInicio = '18:00:00',
        string $horaFin = '20:00:00',
        EstadoSolicitudReserva $estado = EstadoSolicitudReserva::Confirmada,
    ): array {
        $campo = $this->crearCampo();

        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'monto_total' => 100,
            'monto_confirmado' => $estado === EstadoSolicitudReserva::Confirmada ? 100 : null,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
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

        // IMPORTANTE:
        // reservas.confirmado_en es NOT NULL en la BD actual.
        // Para tests de solicitudes no confirmadas, igual creamos la reserva
        // con confirmado_en = now() solo para cumplir la restricción de BD.
        // La regla de negocio la valida MarcarAsistenciaService revisando
        // el estado de la solicitud asociada.
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

        return [$solicitud, $reserva, $campo];
    }
}

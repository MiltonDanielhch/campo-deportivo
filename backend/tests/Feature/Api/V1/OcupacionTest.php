<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EstadoSolicitudReserva;
use App\Models\AsignacionFuncionario;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\HorarioAtencion;
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

class OcupacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_funcionario_control_ve_solo_sus_campos_asignados(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $control = $this->crearFuncionarioControl();
        $admin = $this->crearFuncionarioAdminParametricas();

        $campo1 = $this->crearCampo('CAMPO-1', 'Cancha del Control');
        $campo2 = $this->crearCampo('CAMPO-2', 'Cancha del Admin');

        $this->asignarCampo($control, $campo1);
        $this->asignarCampo($admin, $campo2);

        $this->crearHorarioAtencion($campo1);
        $this->crearHorarioAtencion($campo2);

        Sanctum::actingAs($control);

        $response = $this->getJson('/api/v1/ocupacion/mis-campos?fecha=2026-10-03');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.campo_id', $campo1->id)
            ->assertJsonPath('data.0.campo_nombre', 'Cancha del Control');
    }

    public function test_admin_ve_todos_los_campos_activos(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo1 = $this->crearCampo('CAMPO-1', 'Cancha 1');
        $campo2 = $this->crearCampo('CAMPO-2', 'Cancha 2');

        $this->crearHorarioAtencion($campo1);
        $this->crearHorarioAtencion($campo2);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/ocupacion/mis-campos?fecha=2026-10-03');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_mis_campos_muestra_franjas_con_estado(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $control = $this->crearFuncionarioControl();
        $campo = $this->crearCampo('CAMPO-TEST', 'Cancha Test');
        $this->asignarCampo($control, $campo);
        $this->crearHorarioAtencion($campo);

        // Crear reserva confirmada para 18:00-20:00
        [$solicitud, $reserva] = $this->crearReservaConfirmada($campo, '2026-10-03', '18:00:00', '20:00:00');

        Sanctum::actingAs($control);

        $response = $this->getJson('/api/v1/ocupacion/mis-campos?fecha=2026-10-03');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.abierto', true)
            ->assertJsonPath('data.0.fecha', '2026-10-03');

        // Verificar que hay franjas ocupadas
        $franjas = $response->json('data.0.franjas');
        $franjasOcupadas = collect($franjas)->filter(fn ($f) => $f['estado'] === 'ocupada');

        $this->assertGreaterThanOrEqual(2, $franjasOcupadas->count());
    }

    /**
     * [FIX D-02 / mismatch RES-RSV] El verificador interno DEBE exponer el
     * titular al funcionario autenticado con acceso al campo, para cruzar el
     * documento de identidad en sitio. La privacidad del titular se protege
     * en el canal PÚBLICO (ver SolicitudEstadoControllerTest), no acá.
     *
     * Además acepta ambos formatos: RSV- (codigo_reserva) y RES- (seguimiento).
     */
    public function test_verificar_codigo_valido_devuelve_titular_para_funcionario_con_acceso(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $control = $this->crearFuncionarioControl();
        $campo = $this->crearCampo('CAMPO-TEST', 'Cancha Test');
        $this->asignarCampo($control, $campo);

        [$solicitud, $reserva] = $this->crearReservaConfirmada($campo, '2026-10-03', '18:00:00', '20:00:00');

        Sanctum::actingAs($control);

        // Camino A: por codigo_reserva (RSV-)
        $response = $this->getJson("/api/v1/ocupacion/verificar/{$reserva->codigo_reserva}");

        $response
            ->assertOk()
            ->assertJsonPath('data.codigo_seguimiento', $solicitud->codigo_seguimiento)
            ->assertJsonPath('data.franjas.0.codigo_reserva', $reserva->codigo_reserva)
            ->assertJsonPath('data.franjas.0.id', $reserva->id)
            ->assertJsonPath('data.campo_nombre', 'Cancha Test')
            ->assertJsonPath('data.titular.nombre', 'Juan Pérez')
            ->assertJsonPath('data.titular.ci_nit', '1234567')
            ->assertJsonPath('data.titular.telefono', '70000000');

        // Camino B: por codigo_seguimiento (RES-) → mismo titular, misma franja
        $this->getJson("/api/v1/ocupacion/verificar/{$solicitud->codigo_seguimiento}")
            ->assertOk()
            ->assertJsonPath('data.codigo_seguimiento', $solicitud->codigo_seguimiento)
            ->assertJsonCount(1, 'data.franjas')
            ->assertJsonPath('data.franjas.0.codigo_reserva', $reserva->codigo_reserva);
    }

    public function test_verificar_codigo_de_campo_no_asignado_devuelve_404(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $control = $this->crearFuncionarioControl();
        $campoAsignado = $this->crearCampo('CAMPO-ASIGNADO', 'Cancha Asignada');
        $campoNoAsignado = $this->crearCampo('CAMPO-NO-ASIGNADO', 'Cancha No Asignada');

        $this->asignarCampo($control, $campoAsignado);

        [$solicitud, $reserva] = $this->crearReservaConfirmada($campoNoAsignado, '2026-10-03', '18:00:00', '20:00:00');

        Sanctum::actingAs($control);

        $response = $this->getJson("/api/v1/ocupacion/verificar/{$reserva->codigo_reserva}");

        $response
            ->assertNotFound()
            ->assertJsonPath('message', 'Código de reserva no encontrado.');
    }

    public function test_admin_puede_verificar_cualquier_codigo(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();
        $campo = $this->crearCampo('CAMPO-TEST', 'Cancha Test');

        [$solicitud, $reserva] = $this->crearReservaConfirmada($campo, '2026-10-03', '18:00:00', '20:00:00');

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/ocupacion/verificar/{$reserva->codigo_reserva}");

        $response
            ->assertOk()
            ->assertJsonPath('data.franjas.0.codigo_reserva', $reserva->codigo_reserva);
    }

    public function test_verificar_codigo_inexistente_devuelve_404(): void
    {
        $control = $this->crearFuncionarioControl();

        Sanctum::actingAs($control);

        $response = $this->getJson('/api/v1/ocupacion/verificar/CODIGO-INEXISTENTE');

        $response
            ->assertNotFound()
            ->assertJsonPath('message', 'Código de reserva no encontrado.');
    }

    public function test_fecha_invalida_devuelve_422(): void
    {
        $control = $this->crearFuncionarioControl();

        Sanctum::actingAs($control);

        $this->getJson('/api/v1/ocupacion/mis-campos?fecha=invalida')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Fecha inválida. Use formato YYYY-MM-DD.');
    }

    public function test_usuario_no_autenticado_recibe_401(): void
    {
        $this->getJson('/api/v1/ocupacion/mis-campos?fecha=2026-10-03')
            ->assertUnauthorized();

        $this->getJson('/api/v1/ocupacion/verificar/CODIGO')
            ->assertUnauthorized();
    }

    public function test_rol_sin_permiso_recibe_403(): void
    {
        $rol = Rol::create([
            'nombre' => 'visitante',
            'descripcion' => 'Rol sin permisos',
            'permisos' => [],
        ]);

        $funcionario = Funcionario::create([
            'nombre_completo' => 'Visitante',
            'ci' => '000000',
            'usuario' => 'visitante',
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
            'mamore_id' => 999999,
        ]);

        Sanctum::actingAs($funcionario);

        $this->getJson('/api/v1/ocupacion/mis-campos?fecha=2026-10-03')
            ->assertForbidden();
    }


    public function test_mapa_global_devuelve_todos_los_campos_visibles(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo1 = $this->crearCampo('CAMPO-1', 'Cancha 1');
        $campo2 = $this->crearCampo('CAMPO-2', 'Cancha 2');
        $campo3 = $this->crearCampo('CAMPO-3', 'Cancha 3');

        $campo1->update(['estado' => 'activo']);
        $campo2->update(['estado' => 'activo']);
        $campo3->update(['estado' => 'mantenimiento']);

        $this->crearHorarioAtencion($campo1);
        $this->crearHorarioAtencion($campo2);
        $this->crearHorarioAtencion($campo3);

        // Campo inactivo NO debe aparecer
        $campo4 = $this->crearCampo('CAMPO-4', 'Cancha Inactiva');
        $campo4->update(['estado' => 'inactivo']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/ocupacion/mapa-global?fecha=2026-10-03');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total_campos', 3)
            ->assertJsonPath('meta.activos', 2)
            ->assertJsonPath('meta.en_mantenimiento', 1);
    }

    public function test_mapa_global_incluye_coordenadas_y_vinculacion_sireb(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo = $this->crearCampo('CAMPO-TEST', 'Cancha Test');
        $campo->update([
            'latitud' => -14.83333333,
            'longitud' => -64.90000000,
            'servicio_sireb_id' => '11111111-1111-4111-8111-111111111111',
            'servicio_sireb_codigo' => 'SEDEDE-CS1',
        ]);

        $this->crearHorarioAtencion($campo);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/ocupacion/mapa-global?fecha=2026-10-03');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.latitud', -14.83333333)
            ->assertJsonPath('data.0.longitud', -64.90000000)
            ->assertJsonPath('data.0.vinculacion_sireb.vinculado', true)
            ->assertJsonPath('data.0.vinculacion_sireb.servicio_sireb_codigo', 'SEDEDE-CS1');
    }

    public function test_mapa_global_incluye_resumen_de_ocupacion(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo = $this->crearCampo('CAMPO-TEST', 'Cancha Test');
        $this->crearHorarioAtencion($campo);

        // Crear 2 reservas confirmadas
        $this->crearReservaConfirmada($campo, '2026-10-03', '18:00:00', '20:00:00');
        $this->crearReservaConfirmada($campo, '2026-10-03', '20:00:00', '22:00:00');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/ocupacion/mapa-global?fecha=2026-10-03');

        $response->assertOk();

        $data = $response->json('data.0');

        $this->assertArrayHasKey('resumen_ocupacion', $data);
        $this->assertEquals(14, $data['resumen_ocupacion']['franjas_totales']); // 08:00-22:00 = 14 franjas
        $this->assertEquals(4, $data['resumen_ocupacion']['franjas_ocupadas']); // 18-20 y 20-22 = 4 franjas
        $this->assertEquals(10, $data['resumen_ocupacion']['franjas_libres']);
        $this->assertGreaterThan(0, $data['resumen_ocupacion']['porcentaje_ocupacion']);
    }

    public function test_funcionario_control_recibe_403_en_mapa_global(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $control = $this->crearFuncionarioControl();

        Sanctum::actingAs($control);

        $this->getJson('/api/v1/ocupacion/mapa-global?fecha=2026-10-03')
            ->assertForbidden();
    }

    public function test_gerencia_puede_acceder_al_mapa_global(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $gerencia = $this->crearFuncionarioGerencia();

        $campo = $this->crearCampo('CAMPO-TEST', 'Cancha Test');
        $this->crearHorarioAtencion($campo);

        Sanctum::actingAs($gerencia);

        $this->getJson('/api/v1/ocupacion/mapa-global?fecha=2026-10-03')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_mapa_global_fecha_invalida_devuelve_422(): void
    {
        $admin = $this->crearFuncionarioAdminParametricas();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/ocupacion/mapa-global?fecha=invalida')
            ->assertStatus(422);
    }

    public function test_mapa_global_detecta_campo_no_vinculado_a_sireb(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo = $this->crearCampo('CAMPO-TEST', 'Cancha Sin SIREB');
        // No seteamos servicio_sireb_id
        $this->crearHorarioAtencion($campo);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/ocupacion/mapa-global?fecha=2026-10-03');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.vinculacion_sireb.vinculado', false)
            ->assertJsonPath('data.0.vinculacion_sireb.servicio_sireb_id', null);

        $meta = $response->json('meta');
        $this->assertEquals(0, $meta['vinculados_sireb']);
    }

    public function test_verificar_por_codigo_seguimiento_devuelve_todas_las_franjas(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();
        $campo = $this->crearCampo('CAMPO-TEST', 'Cancha Test');

        // Solicitud con 2 franjas confirmadas bajo el mismo codigo_seguimiento
        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-20261003-MULTI',
            'monto_total' => 200,
            'monto_confirmado' => 200,
            'nombre_pagador' => 'Ana López',
            'telefono_pagador' => '71111111',
            'ci_nit_pagador' => '7654321',
            'estado' => EstadoSolicitudReserva::Confirmada,
            'expira_en' => now()->addMinutes(15),
        ]);

        foreach ([['18:00:00', '19:00:00'], ['19:00:00', '20:00:00']] as [$hi, $hf]) {
            $detalle = SolicitudReservaDetalle::create([
                'solicitud_reserva_id' => $solicitud->id,
                'campo_id' => $campo->id,
                'fecha_reserva' => '2026-10-03',
                'hora_inicio' => $hi,
                'hora_fin' => $hf,
                'tarifa_aplicada' => 100,
            ]);
            $detalle->forceFill([
                'estado_solicitud' => EstadoSolicitudReserva::Confirmada->value,
            ])->save();

            Reserva::create([
                'solicitud_reserva_id' => $solicitud->id,
                'solicitud_reserva_detalle_id' => $detalle->id,
                'codigo_reserva' => 'RSV-' . Str::upper(Str::random(5)),
                'campo_id' => $campo->id,
                'fecha_reserva' => '2026-10-03',
                'hora_inicio' => $hi,
                'hora_fin' => $hf,
                'monto_pagado' => 100,
                'confirmado_en' => now(),
            ]);
        }

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/ocupacion/verificar/RES-20261003-MULTI')
            ->assertOk()
            ->assertJsonPath('data.codigo_seguimiento', 'RES-20261003-MULTI')
            ->assertJsonCount(2, 'data.franjas')
            ->assertJsonPath('data.titular.nombre', 'Ana López');
    }

    public function test_verificar_codigo_seguimiento_no_asignado_devuelve_404_uniforme(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $control = $this->crearFuncionarioControl();
        $campoAsignado = $this->crearCampo('CAMPO-A', 'Cancha A');
        $campoNoAsignado = $this->crearCampo('CAMPO-B', 'Cancha B');
        $this->asignarCampo($control, $campoAsignado);

        // Solicitud RES- en campo NO asignado al control
        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-20261003-NOAS',
            'monto_total' => 100,
            'monto_confirmado' => 100,
            'nombre_pagador' => 'Oculto',
            'telefono_pagador' => '72222222',
            'ci_nit_pagador' => '9999999',
            'estado' => EstadoSolicitudReserva::Confirmada,
            'expira_en' => now()->addMinutes(15),
        ]);
        $detalle = SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campoNoAsignado->id,
            'fecha_reserva' => '2026-10-03',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '19:00:00',
            'tarifa_aplicada' => 100,
        ]);
        $detalle->forceFill([
            'estado_solicitud' => EstadoSolicitudReserva::Confirmada->value,
        ])->save();
        Reserva::create([
            'solicitud_reserva_id' => $solicitud->id,
            'solicitud_reserva_detalle_id' => $detalle->id,
            'codigo_reserva' => 'RSV-NOASIGN',
            'campo_id' => $campoNoAsignado->id,
            'fecha_reserva' => '2026-10-03',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '19:00:00',
            'monto_pagado' => 100,
            'confirmado_en' => now(),
        ]);

        Sanctum::actingAs($control);

        // 404 uniforme: no filtra existencia ni expone el titular 'Oculto'
        $this->getJson('/api/v1/ocupacion/verificar/RES-20261003-NOAS')
            ->assertNotFound()
            ->assertJsonPath('message', 'Código de reserva no encontrado.');
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function crearFuncionarioAdmin(): Funcionario
    {
        $rol = $this->crearRol('admin_reservas');

        return Funcionario::create([
            'nombre_completo' => 'Admin Ocupación',
            'ci' => '111111',
            'usuario' => 'admin-ocupacion-'.Str::random(6),
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
            'nombre_completo' => 'Control Ocupación',
            'ci' => '222222',
            'usuario' => 'control-ocupacion-'.Str::random(6),
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

    private function crearCampo(string $codigo, string $nombre): CampoDeportivo
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
            'codigo' => $codigo,
            'nombre' => $nombre,
            'direccion' => 'Dirección de prueba',
            'latitud' => -14.83333333,
            'longitud' => -64.90000000,
            'estado' => 'activo',
            'hora_inicio_noche' => '18:00:00',
        ]);
    }

    private function crearHorarioAtencion(CampoDeportivo $campo): void
    {
        // Crear horario para todos los días de la semana
        for ($dia = 1; $dia <= 7; $dia++) {
            HorarioAtencion::create([
                'campo_id' => $campo->id,
                'dia_semana' => $dia,
                'hora_apertura' => '08:00:00',
                'hora_cierre' => '22:00:00',
            ]);
        }
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
     * @return array{0: SolicitudReserva, 1: Reserva}
     */
    private function crearReservaConfirmada(
        CampoDeportivo $campo,
        string $fecha,
        string $horaInicio,
        string $horaFin
    ): array {
        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'monto_total' => 100,
            'monto_confirmado' => 100,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'estado' => EstadoSolicitudReserva::Confirmada,
            'expira_en' => now()->addMinutes(15),
        ]);

        // Crear detalle y forzar estado_solicitud
        $detalle = SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'tarifa_aplicada' => 100,
        ]);

        // Forzar el estado_solicitud denormalizado
        $detalle->forceFill([
            'estado_solicitud' => EstadoSolicitudReserva::Confirmada->value,
        ])->save();

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

        return [$solicitud, $reserva];
    }

    private function crearFuncionarioGerencia(): Funcionario
    {
        $rol = $this->crearRol('gerencia');
        $rol->update(['permisos' => ['*']]);

        return Funcionario::create([
            'nombre_completo' => 'Gerencia',
            'ci' => '333333',
            'usuario' => 'gerencia-'.Str::random(6),
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
            'mamore_id' => random_int(100000, 999999),
        ]);
    }

    private function crearFuncionarioAdminParametricas(): Funcionario
    {
        $rol = $this->crearRol('admin_parametricas');

        return Funcionario::create([
            'nombre_completo' => 'Admin Paramétricas',
            'ci' => '444444',
            'usuario' => 'admin-param-'.Str::random(6),
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
            'mamore_id' => random_int(100000, 999999),
        ]);
    }
}

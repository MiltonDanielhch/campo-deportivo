<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EstadoSolicitudReserva;
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

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────
    // Ingresos por campo
    // ─────────────────────────────────────────────

    public function test_ingresos_por_campo_suma_correctamente(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo = $this->crearCampo('CAMPO-1', 'Cancha 1');

        // 3 reservas confirmadas de Bs 100 cada una, en horarios distintos
        $this->crearReservaConfirmada($campo, '2026-10-01', '08:00:00', '10:00:00', 100);
        $this->crearReservaConfirmada($campo, '2026-10-01', '10:00:00', '12:00:00', 100);
        $this->crearReservaConfirmada($campo, '2026-10-01', '14:00:00', '16:00:00', 100);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reportes/ingresos?desde=2026-10-01&hasta=2026-10-31');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.campo_id', $campo->id)
            ->assertJsonPath('data.0.campo_nombre', 'Cancha 1')
            ->assertJsonPath('data.0.total_ingresos', 300)
            ->assertJsonPath('data.0.total_reservas', 3)
            ->assertJsonPath('meta.total_general', 300);
    }

    public function test_ingresos_solo_cuenta_reservas_confirmadas(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo = $this->crearCampo('CAMPO-1', 'Cancha 1');

        // 1 reserva confirmada
        $this->crearReservaConfirmada($campo, '2026-10-01', '18:00:00', '20:00:00', 100);

        // 1 solicitud pendiente (NO debe contar)
        $this->crearSolicitudPendiente($campo);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reportes/ingresos?desde=2026-10-01&hasta=2026-10-31');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.total_ingresos', 100)
            ->assertJsonPath('data.0.total_reservas', 1);
    }

    public function test_ingresos_respeta_rango_de_fechas(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo = $this->crearCampo('CAMPO-1', 'Cancha 1');

        // Reserva dentro del rango
        $this->crearReservaConfirmada($campo, '2026-10-15', '18:00:00', '20:00:00', 100);

        // Reserva fuera del rango
        $this->crearReservaConfirmada($campo, '2026-09-15', '18:00:00', '20:00:00', 200);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reportes/ingresos?desde=2026-10-01&hasta=2026-10-31');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.total_ingresos', 100)
            ->assertJsonPath('data.0.total_reservas', 1);
    }

    public function test_ingresos_filtra_por_campo_id(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo1 = $this->crearCampo('CAMPO-1', 'Cancha 1');
        $campo2 = $this->crearCampo('CAMPO-2', 'Cancha 2');

        $this->crearReservaConfirmada($campo1, '2026-10-15', '18:00:00', '20:00:00', 100);
        $this->crearReservaConfirmada($campo2, '2026-10-15', '18:00:00', '20:00:00', 200);

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            "/api/v1/reportes/ingresos?desde=2026-10-01&hasta=2026-10-31&campo_id={$campo1->id}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.campo_id', $campo1->id)
            ->assertJsonPath('data.0.total_ingresos', 100);
    }

    // ─────────────────────────────────────────────
    // Histograma de horas pico
    // ─────────────────────────────────────────────

    public function test_horas_pico_agrupa_por_hora(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo = $this->crearCampo('CAMPO-1', 'Cancha 1');

        // 2 reservas a las 18:00
        $this->crearReservaConfirmada($campo, '2026-10-01', '18:00:00', '20:00:00', 100);
        $this->crearReservaConfirmada($campo, '2026-10-02', '18:00:00', '20:00:00', 100);

        // 1 reserva a las 20:00
        $this->crearReservaConfirmada($campo, '2026-10-03', '20:00:00', '22:00:00', 100);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reportes/horas-pico?desde=2026-10-01&hasta=2026-10-31');

        $response
            ->assertOk()
            ->assertJsonCount(24, 'data') // Las 24 horas del día
            ->assertJsonPath('meta.hora_pico', 18) // La hora con más reservas
            ->assertJsonPath('meta.total_reservas', 3);

        // Verificar la hora 18 tiene 2 reservas
        $hora18 = collect($response->json('data'))->firstWhere('hora', 18);
        $this->assertEquals(2, $hora18['total_reservas']);

        // Verificar la hora 20 tiene 1 reserva
        $hora20 = collect($response->json('data'))->firstWhere('hora', 20);
        $this->assertEquals(1, $hora20['total_reservas']);
    }

    public function test_horas_pico_sin_reservas_devuelve_ceros(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reportes/horas-pico?desde=2026-10-01&hasta=2026-10-31');

        $response
            ->assertOk()
            ->assertJsonCount(24, 'data')
            ->assertJsonPath('meta.hora_pico', null)
            ->assertJsonPath('meta.total_reservas', 0);
    }

    // ─────────────────────────────────────────────
    // Clientes frecuentes
    // ─────────────────────────────────────────────

    public function test_clientes_frecuentes_agrupa_por_ci_nit(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo = $this->crearCampo('CAMPO-1', 'Cancha 1');

        // Primera solicitud
        $solicitud1 = $this->crearSolicitudConfirmadaYDevolver($campo, '1234567', 'Juan Pérez', 100, '2026-10-15', '08:00:00', '10:00:00');

        // Forzar timestamp anterior (hace 10 segundos)
        $solicitud1->forceFill(['creado_en' => now()->subSeconds(10)])->save();

        // Segunda solicitud (timestamp actual)
        $this->crearSolicitudConfirmada($campo, '1234567', 'juan perez', 150, '2026-10-15', '10:00:00', '12:00:00');

        // Cliente diferente
        $this->crearSolicitudConfirmada($campo, '9876543', 'María Gómez', 200, '2026-10-15', '14:00:00', '16:00:00');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reportes/clientes-frecuentes?desde=2026-10-01&hasta=2026-10-31');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total_clientes', 2);

        // El primer cliente debe ser Juan Pérez (2 reservas)
        $cliente1 = $response->json('data.0');
        $this->assertEquals('1234567', $cliente1['clave_agrupacion']);
        $this->assertEquals(2, $cliente1['total_reservas']);
        $this->assertEquals(250, $cliente1['monto_total_gastado']);
        // El nombre más reciente debe ser el último registrado
        $this->assertEquals('juan perez', $cliente1['nombre_mas_reciente']);
    }

    public function test_clientes_frecuentes_solo_cuenta_confirmadas(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo = $this->crearCampo('CAMPO-1', 'Cancha 1');

        // 1 confirmada
        $this->crearSolicitudConfirmada($campo, '1234567', 'Juan Pérez', 100, '2026-10-15', '08:00:00', '10:00:00');

        // 1 pendiente (NO debe contar)
        $this->crearSolicitudPendiente($campo, '1234567', '2026-10-15', '10:00:00', '12:00:00');

        // 1 expirada (NO debe contar)
        $this->crearSolicitudExpirada($campo, '1234567', '2026-10-15', '14:00:00', '16:00:00');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reportes/clientes-frecuentes?desde=2026-10-01&hasta=2026-10-31');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.total_reservas', 1)
            ->assertJsonPath('data.0.monto_total_gastado', 100);
    }

    public function test_clientes_frecuentes_usa_telefono_si_no_hay_ci(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();

        $campo = $this->crearCampo('CAMPO-1', 'Cancha 1');

        // Cliente sin CI/NIT (usa teléfono como clave)
        $this->crearSolicitudConfirmadaSinCI($campo, '70000000', 'Cliente Sin CI', 100);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reportes/clientes-frecuentes?desde=2026-10-01&hasta=2026-10-31');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.clave_agrupacion', '70000000');
    }

    // ─────────────────────────────────────────────
    // Seguridad y validación
    // ─────────────────────────────────────────────

    public function test_funcionario_control_recibe_403_en_reportes(): void
    {
        $control = $this->crearFuncionarioControl();

        Sanctum::actingAs($control);

        $this->getJson('/api/v1/reportes/ingresos?desde=2026-10-01&hasta=2026-10-31')
            ->assertForbidden();

        $this->getJson('/api/v1/reportes/horas-pico?desde=2026-10-01&hasta=2026-10-31')
            ->assertForbidden();

        $this->getJson('/api/v1/reportes/clientes-frecuentes?desde=2026-10-01&hasta=2026-10-31')
            ->assertForbidden();
    }

    public function test_admin_reservas_recibe_403_en_reportes(): void
    {
        $adminReservas = $this->crearFuncionarioAdminReservas();

        Sanctum::actingAs($adminReservas);

        $this->getJson('/api/v1/reportes/ingresos?desde=2026-10-01&hasta=2026-10-31')
            ->assertForbidden();
    }

    public function test_gerencia_puede_acceder_a_reportes(): void
    {
        $gerencia = $this->crearFuncionarioGerencia();

        Sanctum::actingAs($gerencia);

        $this->getJson('/api/v1/reportes/ingresos?desde=2026-10-01&hasta=2026-10-31')
            ->assertOk();

        $this->getJson('/api/v1/reportes/horas-pico?desde=2026-10-01&hasta=2026-10-31')
            ->assertOk();

        $this->getJson('/api/v1/reportes/clientes-frecuentes?desde=2026-10-01&hasta=2026-10-31')
            ->assertOk();
    }

    public function test_fechas_invalidas_devuelven_422(): void
    {
        $admin = $this->crearFuncionarioAdminParametricas();

        Sanctum::actingAs($admin);

        // Falta 'desde'
        $this->getJson('/api/v1/reportes/ingresos?hasta=2026-10-31')
            ->assertStatus(422);

        // Falta 'hasta'
        $this->getJson('/api/v1/reportes/ingresos?desde=2026-10-01')
            ->assertStatus(422);

        // Formato inválido
        $this->getJson('/api/v1/reportes/ingresos?desde=invalido&hasta=2026-10-31')
            ->assertStatus(422);

        // Desde posterior a hasta
        $this->getJson('/api/v1/reportes/ingresos?desde=2026-10-31&hasta=2026-10-01')
            ->assertStatus(422);
    }

    public function test_usuario_no_autenticado_recibe_401(): void
    {
        $this->getJson('/api/v1/reportes/ingresos?desde=2026-10-01&hasta=2026-10-31')
            ->assertUnauthorized();
    }

    public function test_reportes_incluyen_nota_vista_operativa(): void
    {
        $admin = $this->crearFuncionarioAdminParametricas();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reportes/ingresos?desde=2026-10-01&hasta=2026-10-31');

        $response->assertOk();

        $nota = $response->json('meta.nota');
        $this->assertStringContainsString('vista operativa', $nota);
        $this->assertStringContainsString('Paitití', $nota);
        $this->assertStringContainsString('SIREB', $nota);
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function crearFuncionarioAdminParametricas(): Funcionario
    {
        $rol = $this->crearRol('admin_parametricas');

        return Funcionario::create([
            'nombre_completo' => 'Admin Paramétricas',
            'ci' => '111111',
            'usuario' => 'admin-param-'.Str::random(6),
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
            'mamore_id' => random_int(100000, 999999),
        ]);
    }

    private function crearFuncionarioAdminReservas(): Funcionario
    {
        $rol = $this->crearRol('admin_reservas');

        return Funcionario::create([
            'nombre_completo' => 'Admin Reservas',
            'ci' => '222222',
            'usuario' => 'admin-reservas-'.Str::random(6),
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
            'nombre_completo' => 'Control',
            'ci' => '333333',
            'usuario' => 'control-'.Str::random(6),
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
            'mamore_id' => random_int(100000, 999999),
        ]);
    }

    private function crearFuncionarioGerencia(): Funcionario
    {
        $rol = $this->crearRol('gerencia');
        $rol->update(['permisos' => ['*']]);

        return Funcionario::create([
            'nombre_completo' => 'Gerencia',
            'ci' => '444444',
            'usuario' => 'gerencia-'.Str::random(6),
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

    private function crearReservaConfirmada(
        CampoDeportivo $campo,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        float $monto = 100
    ): void {
        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'monto_total' => $monto,
            'monto_confirmado' => $monto,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'estado' => EstadoSolicitudReserva::Confirmada,
            'expira_en' => now()->addMinutes(15),
        ]);

        $detalle = SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'tarifa_aplicada' => $monto,
        ]);

        $detalle->forceFill([
            'estado_solicitud' => EstadoSolicitudReserva::Confirmada->value,
        ])->save();

        Reserva::create([
            'solicitud_reserva_id' => $solicitud->id,
            'solicitud_reserva_detalle_id' => $detalle->id,
            'codigo_reserva' => 'RSV-'.Str::upper(Str::random(8)),
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'monto_pagado' => $monto,
            'confirmado_en' => now(),
        ]);
    }

    private function crearSolicitudConfirmada(
        CampoDeportivo $campo,
        string $ciNit,
        string $nombre,
        float $monto = 100,
        string $fecha = '2026-10-15',
        string $horaInicio = '18:00:00',
        string $horaFin = '20:00:00'
    ): void {
        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'monto_total' => $monto,
            'monto_confirmado' => $monto,
            'nombre_pagador' => $nombre,
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => $ciNit,
            'estado' => EstadoSolicitudReserva::Confirmada,
            'expira_en' => now()->addMinutes(15),
        ]);

        $detalle = SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'tarifa_aplicada' => $monto,
        ]);

        $detalle->forceFill([
            'estado_solicitud' => EstadoSolicitudReserva::Confirmada->value,
        ])->save();

        Reserva::create([
            'solicitud_reserva_id' => $solicitud->id,
            'solicitud_reserva_detalle_id' => $detalle->id,
            'codigo_reserva' => 'RSV-'.Str::upper(Str::random(8)),
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'monto_pagado' => $monto,
            'confirmado_en' => now(),
        ]);
    }

    /**
     * Igual que crearSolicitudConfirmada pero devuelve la solicitud creada.
     */
    private function crearSolicitudConfirmadaYDevolver(
        CampoDeportivo $campo,
        string $ciNit,
        string $nombre,
        float $monto = 100,
        string $fecha = '2026-10-15',
        string $horaInicio = '18:00:00',
        string $horaFin = '20:00:00'
    ): SolicitudReserva {
        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'monto_total' => $monto,
            'monto_confirmado' => $monto,
            'nombre_pagador' => $nombre,
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => $ciNit,
            'estado' => EstadoSolicitudReserva::Confirmada,
            'expira_en' => now()->addMinutes(15),
        ]);

        $detalle = SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'tarifa_aplicada' => $monto,
        ]);

        $detalle->forceFill([
            'estado_solicitud' => EstadoSolicitudReserva::Confirmada->value,
        ])->save();

        Reserva::create([
            'solicitud_reserva_id' => $solicitud->id,
            'solicitud_reserva_detalle_id' => $detalle->id,
            'codigo_reserva' => 'RSV-'.Str::upper(Str::random(8)),
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'monto_pagado' => $monto,
            'confirmado_en' => now(),
        ]);

        return $solicitud;
    }

    private function crearSolicitudConfirmadaSinCI(
        CampoDeportivo $campo,
        string $telefono,
        string $nombre,
        float $monto = 100,
        string $fecha = '2026-10-15',
        string $horaInicio = '18:00:00',
        string $horaFin = '20:00:00'
    ): void {
        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'monto_total' => $monto,
            'monto_confirmado' => $monto,
            'nombre_pagador' => $nombre,
            'telefono_pagador' => $telefono,
            'ci_nit_pagador' => null,
            'estado' => EstadoSolicitudReserva::Confirmada,
            'expira_en' => now()->addMinutes(15),
        ]);

        $detalle = SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'tarifa_aplicada' => $monto,
        ]);

        $detalle->forceFill([
            'estado_solicitud' => EstadoSolicitudReserva::Confirmada->value,
        ])->save();

        Reserva::create([
            'solicitud_reserva_id' => $solicitud->id,
            'solicitud_reserva_detalle_id' => $detalle->id,
            'codigo_reserva' => 'RSV-'.Str::upper(Str::random(8)),
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'monto_pagado' => $monto,
            'confirmado_en' => now(),
        ]);
    }

    private function crearSolicitudPendiente(
        CampoDeportivo $campo,
        string $ciNit = '1234567',
        string $fecha = '2026-10-15',
        string $horaInicio = '18:00:00',
        string $horaFin = '20:00:00'
    ): void {
        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'monto_total' => 100,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => $ciNit,
            'estado' => EstadoSolicitudReserva::Pendiente,
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

        $detalle->forceFill([
            'estado_solicitud' => EstadoSolicitudReserva::Pendiente->value,
        ])->save();
    }

    private function crearSolicitudExpirada(
        CampoDeportivo $campo,
        string $ciNit = '1234567',
        string $fecha = '2026-10-15',
        string $horaInicio = '18:00:00',
        string $horaFin = '20:00:00'
    ): void {
        $solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'monto_total' => 100,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => $ciNit,
            'estado' => EstadoSolicitudReserva::Expirada,
            'expira_en' => now()->subMinutes(15),
        ]);

        $detalle = SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'tarifa_aplicada' => 100,
        ]);

        $detalle->forceFill([
            'estado_solicitud' => EstadoSolicitudReserva::Expirada->value,
        ])->save();
    }
}

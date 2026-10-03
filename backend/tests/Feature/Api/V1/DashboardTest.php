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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_resumen_devuelve_kpis_del_dia(): void
    {
        $this->travelTo(Carbon::parse('2026-10-04 14:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();
        $campo = $this->crearCampo('CAMPO-1', 'Cancha 1');

        // 2 reservas hoy
        $this->crearReservaConfirmada($campo, '2026-10-04', '08:00:00', '10:00:00', 100);
        $this->crearReservaConfirmada($campo, '2026-10-04', '10:00:00', '12:00:00', 150);

        // 1 reserva ayer (no debe contar)
        $this->crearReservaConfirmada($campo, '2026-10-03', '08:00:00', '10:00:00', 100);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/dashboard/resumen');

        $response
            ->assertOk()
            ->assertJsonPath('data.fecha', '2026-10-04')
            ->assertJsonPath('data.reservas_hoy', 2)
            ->assertJsonPath('data.ingresos_hoy', 250)
            ->assertJsonPath('data.total_campos_activos', 1);
    }

    public function test_resumen_cuenta_campos_ocupados_ahora(): void
    {
        $this->travelTo(Carbon::parse('2026-10-04 14:30:00')); // 14:30

        $admin = $this->crearFuncionarioAdminParametricas();
        $campo1 = $this->crearCampo('CAMPO-1', 'Cancha 1');
        $campo2 = $this->crearCampo('CAMPO-2', 'Cancha 2');

        // Reserva que cubre 14:30 (14:00-16:00)
        $this->crearReservaConfirmada($campo1, '2026-10-04', '14:00:00', '16:00:00', 100);

        // Reserva que NO cubre 14:30 (16:00-18:00)
        $this->crearReservaConfirmada($campo2, '2026-10-04', '16:00:00', '18:00:00', 100);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/dashboard/resumen');

        $response
            ->assertOk()
            ->assertJsonPath('data.campos_ocupados_ahora', 1);
    }

    public function test_resumen_incluye_distribucion_por_hora(): void
    {
        $this->travelTo(Carbon::parse('2026-10-04 14:00:00'));

        $admin = $this->crearFuncionarioAdminParametricas();
        $campo = $this->crearCampo('CAMPO-1', 'Cancha 1');

        // 2 reservas a las 08:00
        $this->crearReservaConfirmada($campo, '2026-10-04', '08:00:00', '10:00:00', 100);
        $this->crearReservaConfirmada($campo, '2026-10-04', '10:00:00', '12:00:00', 100);

        // 1 reserva a las 14:00
        $this->crearReservaConfirmada($campo, '2026-10-04', '14:00:00', '16:00:00', 100);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/dashboard/resumen');

        $response->assertOk();

        $distribucion = $response->json('data.distribucion_hoy');
        $this->assertCount(24, $distribucion);

        // Verificar hora 08 tiene 1 reserva
        $hora08 = collect($distribucion)->firstWhere('hora', 8);
        $this->assertEquals(1, $hora08['total']);

        // Verificar hora 14 tiene 1 reserva
        $hora14 = collect($distribucion)->firstWhere('hora', 14);
        $this->assertEquals(1, $hora14['total']);
    }

    public function test_resumen_incluye_accesos_rapidos_segun_rol(): void
    {
        $admin = $this->crearFuncionarioAdminParametricas();
        $control = $this->crearFuncionarioControl();

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/dashboard/resumen');
        $response->assertOk();

        $accesos = $response->json('data.accesos_rapidos');
        $this->assertContains('reportes', $accesos);
        $this->assertContains('mapa_global', $accesos);

        Sanctum::actingAs($control);
        $response = $this->getJson('/api/v1/dashboard/resumen');
        $response->assertOk();

        $accesos = $response->json('data.accesos_rapidos');
        $this->assertContains('ocupacion', $accesos);
        $this->assertNotContains('reportes', $accesos);
    }

    public function test_usuario_no_autenticado_recibe_401(): void
    {
        $this->getJson('/api/v1/dashboard/resumen')
            ->assertUnauthorized();
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

    private function crearFuncionarioControl(): Funcionario
    {
        $rol = $this->crearRol('funcionario_control');

        return Funcionario::create([
            'nombre_completo' => 'Control',
            'ci' => '222222',
            'usuario' => 'control-'.Str::random(6),
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
}

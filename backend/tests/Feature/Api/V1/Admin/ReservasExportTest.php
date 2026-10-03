<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\EstadoSolicitudReserva;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaDetalle;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservasExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_csv_sin_filtros_incluye_franjas_confirmadas_y_pendientes(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        $this->crearSolicitudConReserva([
            'codigo_seguimiento' => 'RES-CONFIRMADA-EXPORT',
            'nombre_pagador' => 'Pagador Confirmado',
            'telefono_pagador' => '70000001',
            'ci_nit_pagador' => '111000111',
            'campo_nombre' => 'Cancha Export Confirmada',
            'codigo_reserva' => 'RSV-EXPORT1',
            'monto_pagado' => 150.50,
        ]);

        $this->crearSolicitudSinReserva([
            'codigo_seguimiento' => 'RES-PENDIENTE-EXPORT',
            'nombre_pagador' => 'Pagador Pendiente',
            'telefono_pagador' => '70000002',
            'ci_nit_pagador' => '222000222',
            'campo_nombre' => 'Cancha Export Pendiente',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/reservas/export?formato=csv');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $this->assertStringContainsString('RES-CONFIRMADA-EXPORT', $content);
        $this->assertStringContainsString('RSV-EXPORT1', $content);
        $this->assertStringContainsString('150.50', $content);
        $this->assertStringContainsString('Cancha Export Confirmada', $content);

        $this->assertStringContainsString('RES-PENDIENTE-EXPORT', $content);
        $this->assertStringContainsString('Cancha Export Pendiente', $content);
    }

    public function test_export_csv_con_estado_confirmada_excluye_pendientes(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        $this->crearSolicitudConReserva([
            'codigo_seguimiento' => 'RES-SOLO-CONFIRMADA',
            'nombre_pagador' => 'Pagador Solo Confirmada',
            'campo_nombre' => 'Cancha Solo Confirmada',
            'codigo_reserva' => 'RSV-SOLO-CONFIRMADA',
        ]);

        $this->crearSolicitudSinReserva([
            'codigo_seguimiento' => 'RES-SOLO-PENDIENTE',
            'nombre_pagador' => 'Pagador Solo Pendiente',
            'campo_nombre' => 'Cancha Solo Pendiente',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/reservas/export?formato=csv&estado=confirmada');

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('RES-SOLO-CONFIRMADA', $content);
        $this->assertStringNotContainsString('RES-SOLO-PENDIENTE', $content);
    }

    public function test_export_csv_respeta_rango_de_fechas(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        $vieja = $this->crearSolicitudConReserva([
            'codigo_seguimiento' => 'RES-VIEJA-EXPORT',
            'nombre_pagador' => 'Pagador Viejo',
            'campo_nombre' => 'Cancha Vieja',
            'codigo_reserva' => 'RSV-VIEJA',
        ]);

        $vieja->forceFill([
            'creado_en' => now()->subDays(3),
        ])->save();

        $nueva = $this->crearSolicitudConReserva([
            'codigo_seguimiento' => 'RES-NUEVA-EXPORT',
            'nombre_pagador' => 'Pagador Nuevo',
            'campo_nombre' => 'Cancha Nueva',
            'codigo_reserva' => 'RSV-NUEVA',
        ]);

        $nueva->forceFill([
            'creado_en' => now(),
        ])->save();

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/v1/admin/reservas/export?formato=csv&desde='.now()->toDateString()
        );

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('RES-NUEVA-EXPORT', $content);
        $this->assertStringNotContainsString('RES-VIEJA-EXPORT', $content);
    }

    public function test_export_csv_incluye_headers_correctos_y_bom_utf8(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        $this->crearSolicitudConReserva([
            'codigo_seguimiento' => 'RES-HEADERS-EXPORT',
            'nombre_pagador' => 'Pagador Headers',
            'campo_nombre' => 'Cancha Headers',
            'codigo_reserva' => 'RSV-HEADERS',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/reservas/export?formato=csv');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString(
            'filename="reservas-'.now()->format('Y-m-d').'.csv"',
            (string) $response->headers->get('Content-Disposition')
        );

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $sinBom = substr($content, 3);
        $lineas = preg_split('/\r\n|\r|\n/', $sinBom);

        $this->assertSame(
            'codigo_seguimiento,estado,nombre_pagador,telefono_pagador,ci_nit_pagador,codigo_reserva,campo_nombre,fecha_reserva,hora_inicio,hora_fin,monto_pagado,asistencia_marcada_en',
            $lineas[0]
        );
    }

    public function test_funcionario_control_recibe_403_en_export(): void
    {
        $control = $this->crearFuncionarioControl();

        Sanctum::actingAs($control);

        $this->getJson('/api/v1/admin/reservas/export?formato=csv')
            ->assertForbidden();
    }

    public function test_formato_invalido_devuelve_422(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/reservas/export?formato=xlsx')
            ->assertStatus(422);
    }

    public function test_usuario_no_autenticado_recibe_401_en_export(): void
    {
        $this->getJson('/api/v1/admin/reservas/export?formato=csv')
            ->assertUnauthorized();
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function crearFuncionarioAdmin(): Funcionario
    {
        $rol = $this->crearRol('admin_reservas');

        return Funcionario::create([
            'nombre_completo' => 'Admin Export',
            'ci' => '111111',
            'usuario' => 'admin-export-'.Str::random(6),
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
            'nombre_completo' => 'Control Export',
            'ci' => '222222',
            'usuario' => 'control-export-'.Str::random(6),
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

    private function crearCampo(string $nombre): CampoDeportivo
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
            'nombre' => $nombre,
            'direccion' => 'Dirección de prueba',
            'latitud' => -14.83333333,
            'longitud' => -64.90000000,
            'estado' => 'activo',
            'hora_inicio_noche' => '18:00:00',
        ]);
    }

    private function crearSolicitudConReserva(array $overrides = []): SolicitudReserva
    {
        $custom = Arr::only($overrides, [
            'campo_nombre',
            'codigo_reserva',
            'monto_pagado',
        ]);

        $datosSolicitud = Arr::except($overrides, array_keys($custom));

        $solicitud = SolicitudReserva::create(array_merge([
            'codigo_seguimiento' => 'RES-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'monto_total' => 100,
            'monto_confirmado' => 100,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'estado' => EstadoSolicitudReserva::Confirmada,
            'expira_en' => now()->addMinutes(15),
        ], $datosSolicitud));

        $campo = $this->crearCampo($custom['campo_nombre'] ?? 'Cancha Export');

        $detalle = SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => now()->addDay()->toDateString(),
            'hora_inicio' => '18:00:00',
            'hora_fin' => '20:00:00',
            'tarifa_aplicada' => $custom['monto_pagado'] ?? 100,
        ]);

        Reserva::create([
            'solicitud_reserva_id' => $solicitud->id,
            'solicitud_reserva_detalle_id' => $detalle->id,
            'codigo_reserva' => $custom['codigo_reserva'] ?? 'RSV-'.Str::upper(Str::random(5)),
            'campo_id' => $campo->id,
            'fecha_reserva' => $detalle->fecha_reserva,
            'hora_inicio' => $detalle->hora_inicio,
            'hora_fin' => $detalle->hora_fin,
            'monto_pagado' => $custom['monto_pagado'] ?? 100,
            'confirmado_en' => now(),
        ]);

        return $solicitud;
    }

    private function crearSolicitudSinReserva(array $overrides = []): SolicitudReserva
    {
        $custom = Arr::only($overrides, [
            'campo_nombre',
        ]);

        $datosSolicitud = Arr::except($overrides, array_keys($custom));

        $solicitud = SolicitudReserva::create(array_merge([
            'codigo_seguimiento' => 'RES-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'monto_total' => 100,
            'monto_confirmado' => null,
            'nombre_pagador' => 'Pedro Pendiente',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '7654321',
            'estado' => EstadoSolicitudReserva::Pendiente,
            'expira_en' => now()->addMinutes(15),
        ], $datosSolicitud));

        $campo = $this->crearCampo($custom['campo_nombre'] ?? 'Cancha Pendiente');

        SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => now()->addDay()->toDateString(),
            'hora_inicio' => '18:00:00',
            'hora_fin' => '20:00:00',
            'tarifa_aplicada' => 100,
        ]);

        return $solicitud;
    }
}

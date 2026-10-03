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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SolicitudReservaListadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_sin_filtros_devuelve_listado_paginado_con_stats(): void
    {
        $admin = $this->crearFuncionarioAdmin();
        $solicitud = $this->crearSolicitud();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/solicitudes-reserva');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $solicitud->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.stats.total', 1)
            ->assertJsonPath('meta.stats.pendientes', 1)
            ->assertJsonStructure([
                'data',
                'links',
                'meta' => [
                    'current_page',
                    'last_page',
                    'total',
                    'per_page',
                    'stats' => [
                        'total',
                        'pendientes',
                        'confirmadas',
                        'expiradas',
                        'canceladas',
                        'rechazadas',
                        'monto_confirmado',
                    ],
                ],
            ]);
    }

    public function test_index_filtra_por_estado(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        $pendiente = $this->crearSolicitud([
            'estado' => EstadoSolicitudReserva::Pendiente,
        ]);

        $confirmada = $this->crearSolicitud([
            'estado' => EstadoSolicitudReserva::Confirmada,
            'monto_confirmado' => 250,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/solicitudes-reserva?estado=confirmada')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $confirmada->id)
            ->assertJsonPath('meta.stats.confirmadas', 1)
            ->assertJsonPath('meta.stats.pendientes', 1);
    }

    public function test_index_filtra_por_rango_de_fechas(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        $vieja = $this->crearSolicitud();
        $vieja->forceFill(['creado_en' => now()->subDays(3)])->save();

        $nueva = $this->crearSolicitud();
        $nueva->forceFill(['creado_en' => now()])->save();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/solicitudes-reserva?desde='.now()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $nueva->id);

        $this->getJson('/api/v1/admin/solicitudes-reserva?hasta='.now()->subDay()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $vieja->id);
    }

    public function test_index_filtra_por_campo(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        $campoA = $this->crearCampo('CAMPO-A', 'Cancha A');
        $campoB = $this->crearCampo('CAMPO-B', 'Cancha B');

        $solicitudA = $this->crearSolicitud();
        $this->crearDetalle($solicitudA, $campoA);

        $solicitudB = $this->crearSolicitud();
        $this->crearDetalle($solicitudB, $campoB);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/solicitudes-reserva?campo_id='.$campoA->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $solicitudA->id)
            ->assertJsonPath('data.0.detalles.0.campo_nombre', 'Cancha A');
    }

    public function test_index_busca_case_insensitive_por_nombre_pagador(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        $solicitud = $this->crearSolicitud([
            'nombre_pagador' => 'Ana López',
            'ci_nit_pagador' => '987654321',
            'telefono_pagador' => '77788899',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/solicitudes-reserva?buscar=ana')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $solicitud->id);

        $this->getJson('/api/v1/admin/solicitudes-reserva?buscar=987654321')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $solicitud->id);
    }

    public function test_funcionario_control_no_puede_espiar_otro_funcionario(): void
    {
        $control1 = $this->crearFuncionarioControl('control-1', 'Control Uno', 10001);
        $control2 = $this->crearFuncionarioControl('control-2', 'Control Dos', 10002);

        $campo1 = $this->crearCampo('CAMPO-1', 'Cancha del Control 1');
        $campo2 = $this->crearCampo('CAMPO-2', 'Cancha del Control 2');

        $this->asignarCampo($control1, $campo1);
        $this->asignarCampo($control2, $campo2);

        $solicitud1 = $this->crearSolicitud();
        $this->crearDetalle($solicitud1, $campo1);

        $solicitud2 = $this->crearSolicitud();
        $this->crearDetalle($solicitud2, $campo2);

        Sanctum::actingAs($control1);

        $this->getJson('/api/v1/admin/solicitudes-reserva?funcionario_control_id='.$control2->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $solicitud1->id)
            ->assertJsonPath('data.0.detalles.0.campo_nombre', 'Cancha del Control 1');
    }

    public function test_funcionario_control_ve_solo_detalles_de_campos_asignados(): void
    {
        $control = $this->crearFuncionarioControl('control-unico', 'Control Único', 20001);

        $campoAsignado = $this->crearCampo('CAMPO-ASIGNADO', 'Cancha Asignada');
        $campoNoAsignado = $this->crearCampo('CAMPO-NO-ASIGNADO', 'Cancha No Asignada');

        $this->asignarCampo($control, $campoAsignado);

        $solicitud = $this->crearSolicitud();
        $this->crearDetalle($solicitud, $campoAsignado);
        $this->crearDetalle($solicitud, $campoNoAsignado);

        Sanctum::actingAs($control);

        $this->getJson('/api/v1/admin/solicitudes-reserva')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.detalles')
            ->assertJsonPath('data.0.detalles.0.campo_nombre', 'Cancha Asignada');

        $this->getJson('/api/v1/admin/solicitudes-reserva/'.$solicitud->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.solicitud.detalles')
            ->assertJsonPath('data.solicitud.detalles.0.campo_nombre', 'Cancha Asignada');
    }

    public function test_show_incluye_auditoria_ordenada_cronologicamente(): void
    {
        $admin = $this->crearFuncionarioAdmin();
        $solicitud = $this->crearSolicitud();

        $auditoria1 = $this->crearAuditoria($solicitud, 'crear', now()->subHour());
        $auditoria2 = $this->crearAuditoria($solicitud, 'confirmar_cobro', now()->subMinute());

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/solicitudes-reserva/'.$solicitud->id)
            ->assertOk()
            ->assertJsonPath('data.auditoria.0.id', $auditoria1->id)
            ->assertJsonPath('data.auditoria.0.accion', 'crear')
            ->assertJsonPath('data.auditoria.1.id', $auditoria2->id)
            ->assertJsonPath('data.auditoria.1.accion', 'confirmar_cobro');
    }

    public function test_show_devuelve_404_si_la_solicitud_no_existe(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/solicitudes-reserva/'.Str::uuid()->toString())
            ->assertNotFound();
    }

    public function test_funcionario_control_recibe_403_en_show_si_no_tiene_acceso(): void
    {
        $control = $this->crearFuncionarioControl('control-sin-acceso', 'Control Sin Acceso', 30001);
        $otroCampo = $this->crearCampo('CAMPO-OTRO', 'Cancha Otro');

        $solicitud = $this->crearSolicitud();
        $this->crearDetalle($solicitud, $otroCampo);

        Sanctum::actingAs($control);

        $this->getJson('/api/v1/admin/solicitudes-reserva/'.$solicitud->id)
            ->assertForbidden();
    }

    public function test_usuario_no_autenticado_recibe_401(): void
    {
        $this->getJson('/api/v1/admin/solicitudes-reserva')
            ->assertUnauthorized();
    }

    public function test_rol_sin_permiso_recibe_403(): void
    {
        $rol = Rol::create([
            'nombre' => 'visitante',
            'descripcion' => 'Rol sin permisos operativos',
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

        $this->getJson('/api/v1/admin/solicitudes-reserva')
            ->assertForbidden();
    }

    public function test_funcionario_control_no_puede_anular_liquidacion(): void
    {
        $control = $this->crearFuncionarioControl('control-anulacion', 'Control Anulación', 40001);
        $solicitud = $this->crearSolicitud();

        Sanctum::actingAs($control);

        $this->postJson('/api/v1/admin/solicitudes-reserva/'.$solicitud->id.'/anular-liquidacion', [
            'motivo' => 'prueba',
        ])->assertForbidden();
    }

    public function test_per_page_tiene_maximo_100(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        $this->crearSolicitud();
        $this->crearSolicitud();
        $this->crearSolicitud();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/solicitudes-reserva?per_page=101')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_estado_invalido_devuelve_422(): void
    {
        $admin = $this->crearFuncionarioAdmin();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/solicitudes-reserva?estado=invalido')
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');
    }

    public function test_resource_publico_no_expone_datos_de_contacto(): void
    {
        $solicitud = $this->crearSolicitud([
            'nombre_pagador' => 'Usuario Público Único',
            'telefono_pagador' => '66655544',
            'ci_nit_pagador' => '888777666',
            'referencia_recaudaciones' => null,
        ]);

        $response = $this->getJson('/api/v1/public/solicitudes-reserva/'.$solicitud->codigo_seguimiento.'/estado');

        $response->assertOk();

        $this->assertStringNotContainsString('Usuario Público Único', $response->getContent());
        $this->assertStringNotContainsString('66655544', $response->getContent());
        $this->assertStringNotContainsString('888777666', $response->getContent());
    }

    // ─────────────────────────────────────────────
    // Helpers de test
    // ─────────────────────────────────────────────

    private function crearFuncionarioAdmin(): Funcionario
    {
        $rol = Rol::create([
            'nombre' => 'admin_reservas',
            'descripcion' => 'Administrador de reservas',
            'permisos' => [],
        ]);

        return Funcionario::create([
            'nombre_completo' => 'Admin Reservas',
            'ci' => '111111',
            'usuario' => 'admin-'.$rol->id,
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
            'mamore_id' => random_int(100000, 999999),
        ]);
    }

    private function crearFuncionarioControl(string $usuario, string $nombre, int $mamoreId): Funcionario
    {
        $rol = Rol::where('nombre', 'funcionario_control')->first();

        if (! $rol) {
            $rol = Rol::create([
                'nombre' => 'funcionario_control',
                'descripcion' => 'Funcionario de control',
                'permisos' => [],
            ]);
        }

        return Funcionario::create([
            'nombre_completo' => $nombre,
            'ci' => (string) $mamoreId,
            'usuario' => $usuario,
            'password_hash' => bcrypt('secret'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
            'mamore_id' => $mamoreId,
        ]);
    }

    private function crearCampo(string $codigo, string $nombre): CampoDeportivo
    {
        $tipo = TipoCampo::where('nombre', 'Futsal')->first();

        if (! $tipo) {
            $tipo = TipoCampo::forceCreate([
                'nombre' => 'Futsal',
                'descripcion' => 'Tipo de prueba',
                'estado' => 'activo',
            ]);
        }

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

    private function asignarCampo(Funcionario $funcionario, CampoDeportivo $campo): AsignacionFuncionario
    {
        return AsignacionFuncionario::create([
            'funcionario_id' => $funcionario->id,
            'campo_id' => $campo->id,
            'asignado_en' => now(),
        ]);
    }

    private function crearSolicitud(array $overrides = []): SolicitudReserva
    {
        return SolicitudReserva::create(array_merge([
            'codigo_seguimiento' => 'RES-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'monto_total' => 100,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'estado' => EstadoSolicitudReserva::Pendiente,
            'expira_en' => now()->addMinutes(15),
        ], $overrides));
    }

    private function crearDetalle(SolicitudReserva $solicitud, CampoDeportivo $campo): SolicitudReservaDetalle
    {
        return SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => now()->addDay()->toDateString(),
            'hora_inicio' => '18:00:00',
            'hora_fin' => '20:00:00',
            'tarifa_aplicada' => 100,
        ]);
    }

    private function crearReserva(SolicitudReserva $solicitud, SolicitudReservaDetalle $detalle, CampoDeportivo $campo): Reserva
    {
        return Reserva::create([
            'solicitud_reserva_id' => $solicitud->id,
            'solicitud_reserva_detalle_id' => $detalle->id,
            'codigo_reserva' => 'RSV-'.Str::upper(Str::random(5)),
            'campo_id' => $campo->id,
            'fecha_reserva' => $detalle->fecha_reserva,
            'hora_inicio' => $detalle->hora_inicio,
            'hora_fin' => $detalle->hora_fin,
            'monto_pagado' => $detalle->tarifa_aplicada,
            'confirmado_en' => now(),
        ]);
    }

    private function crearAuditoria(SolicitudReserva $solicitud, string $accion, $fecha): Auditoria
    {
        $auditoria = Auditoria::create([
            'tabla' => 'solicitudes_reserva',
            'registro_id' => $solicitud->id,
            'accion' => $accion,
            'usuario_id' => null,
            'datos_anteriores' => null,
            'datos_nuevos' => ['accion' => $accion],
        ]);

        $auditoria->forceFill(['fecha' => $fecha])->save();

        return $auditoria;
    }
}

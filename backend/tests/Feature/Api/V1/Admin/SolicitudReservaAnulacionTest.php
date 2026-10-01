<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\EstadoSolicitudReserva;
use App\Models\Funcionario;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SolicitudReservaAnulacionTest extends TestCase
{
    use RefreshDatabase;

    private Funcionario $admin;
    private SolicitudReserva $solicitud;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Rol::firstOrCreate(
            ['nombre' => 'admin_reservas'],
            ['descripcion' => 'Administrador de reservas', 'permisos' => ['*']]
        );

        $this->admin = Funcionario::create([
            'id' => Str::uuid()->toString(),
            'nombre_completo' => 'Admin Test',
            'ci' => '1234567',
            'usuario' => 'admin_test_' . Str::random(4),
            'password_hash' => bcrypt('password'),
            'estado' => 'activo',
            'rol_id' => $rol->id,
        ]);

        $this->solicitud = SolicitudReserva::create([
            'id' => Str::uuid()->toString(),
            'codigo_seguimiento' => 'RES-20261001-TEST01',
            'monto_total' => 100,
            'nombre_pagador' => 'Juan Test',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'estado' => EstadoSolicitudReserva::Pendiente,
            'referencia_recaudaciones' => 'TEST-1234-567',
            'liquidacion_id' => Str::uuid()->toString(),
            'creado_en' => now(),
            'expira_en' => now()->addMinutes(15),
        ]);
    }

    public function test_anular_liquidacion_exitosa(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/admin/solicitudes-reserva/{$this->solicitud->id}/anular-liquidacion", [
            'motivo' => 'Cliente solicita cancelación por emergencia médica',
        ])
            ->dump()
            ->assertJsonStructure(['message']);

        $this->solicitud->refresh();
        $this->assertEquals(EstadoSolicitudReserva::Cancelada, $this->solicitud->estado);
        $this->assertEquals('anulacion_manual_admin', $this->solicitud->motivo_rechazo);
    }

    public function test_anular_requiere_motivo_minimo(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/admin/solicitudes-reserva/{$this->solicitud->id}/anular-liquidacion", [
            'motivo' => 'corto',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }

    public function test_anular_sin_liquidacion_retorna_422(): void
    {
        Sanctum::actingAs($this->admin);

        $this->solicitud->update([
            'liquidacion_id' => null,
            'referencia_recaudaciones' => null,
        ]);

        $this->postJson("/api/v1/admin/solicitudes-reserva/{$this->solicitud->id}/anular-liquidacion", [
            'motivo' => 'Motivo válido de más de diez caracteres',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Esta solicitud no tiene liquidación creada en SIREB.']);
    }

    public function test_anular_solicitud_ya_confirmada_retorna_422(): void
    {
        Sanctum::actingAs($this->admin);

        $this->solicitud->update(['estado' => EstadoSolicitudReserva::Confirmada]);

        $this->postJson("/api/v1/admin/solicitudes-reserva/{$this->solicitud->id}/anular-liquidacion", [
            'motivo' => 'Motivo válido de más de diez caracteres',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Solo se pueden anular liquidaciones de solicitudes pendientes.']);
    }

    public function test_usuario_sin_rol_no_puede_anular(): void
    {
        $rol = Rol::firstOrCreate(
            ['nombre' => 'consultor'],
            ['descripcion' => 'Solo consulta', 'permisos' => []]
        );

        $usuario = Funcionario::create([
            'id' => Str::uuid()->toString(),
            'nombre_completo' => 'Consultor Test',
            'ci' => '7654321',
            'usuario' => 'consultor_test_' . Str::random(4),
            'password_hash' => bcrypt('password'),
            'estado' => 'activo',
            'rol_id' => $rol->id,
        ]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/v1/admin/solicitudes-reserva/{$this->solicitud->id}/anular-liquidacion", [
            'motivo' => 'Motivo válido de más de diez caracteres',
        ])
            ->assertStatus(403);
    }

    public function test_refrescar_sireb_retorna_estado(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/admin/solicitudes-reserva/{$this->solicitud->id}/refrescar-sireb")
            ->assertOk()
            ->assertJsonStructure(['data' => ['estado_sireb', 'puede_anularse']]);
    }

    public function test_detalle_incluye_info_sireb(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/admin/solicitudes-reserva/{$this->solicitud->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['solicitud', 'estado_sireb', 'puede_anularse']]);
    }
}

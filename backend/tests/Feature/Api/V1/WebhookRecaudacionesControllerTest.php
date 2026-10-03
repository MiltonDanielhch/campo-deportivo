<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EstadoSolicitudReserva;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\HorarioAtencion;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaDetalle;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Webhook idempotente de confirmación del Core (HU-D4).
 * En testing el binding activo es el Simulado, que verifica firma
 * con el header fijo X-Test-Signature: test.
 */
class WebhookRecaudacionesControllerTest extends TestCase
{
    use RefreshDatabase;

    private const FIRMA_VALIDA = ['X-Test-Signature' => 'test'];

    private SolicitudReserva $solicitud;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Rol::create(['nombre' => 'admin_test', 'permisos' => ['*']]);
        $admin = Funcionario::create([
            'nombre_completo' => 'Admin Test',
            'ci' => 'TEST-' . uniqid(),
            'usuario' => 'admin_' . uniqid(),
            'password_hash' => bcrypt('x'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
        ]);

        $tipo = TipoCampo::create(['nombre' => 'Fútbol', 'estado' => 'activo']);
        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'TEST-01',
            'nombre' => 'Campo Test',
            'direccion' => 'Dirección',
            'latitud' => -14.84,
            'longitud' => -64.90,
            'estado' => 'activo',
        ]);

        for ($dia = 1; $dia <= 7; $dia++) {
            HorarioAtencion::create([
                'campo_id' => $campo->id,
                'dia_semana' => $dia,
                'hora_apertura' => '08:00',
                'hora_cierre' => '20:00',
            ]);
        }

        TarifaCampo::create([
            'campo_id' => $campo->id,
            'tipo_tarifa' => 'diurna',
            'precio_por_hora' => 150,
            'vigente_desde' => now(),
            'creado_por' => $admin->id,
        ]);

        $this->solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-20260916-WEBH01',
            'monto_total' => 300.00,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'referencia_recaudaciones' => 'CORE-SIM-WEBH01',
            'estado' => EstadoSolicitudReserva::Pendiente,
            'expira_en' => now()->addMinutes(15),
        ]);

        foreach ([['10:00', '11:00'], ['11:00', '12:00']] as [$ini, $fin]) {
            SolicitudReservaDetalle::create([
                'solicitud_reserva_id' => $this->solicitud->id,
                'campo_id' => $campo->id,
                'fecha_reserva' => now()->addDay(),
                'hora_inicio' => $ini,
                'hora_fin' => $fin,
                'tarifa_aplicada' => 150.00,
            ]);
        }
    }

    private function payload(?string $referenciaCore = 'CORE-SIM-WEBH01', ?string $referenciaExterna = null): array
    {
        $data = ['monto_confirmado' => 300.00];

        if ($referenciaCore !== null) {
            $data['referencia_recaudaciones'] = $referenciaCore;
        }
        if ($referenciaExterna !== null) {
            $data['referencia_externa'] = $referenciaExterna;
        }

        return $data;
    }

    public function test_firma_invalida_responde_401_y_no_crea_reservas(): void
    {
        $response = $this->postJson(
            '/api/v1/webhooks/recaudaciones',
            $this->payload(),
            ['X-Test-Signature' => 'firma-falsa'],
        );

        $response->assertStatus(401);
        $this->assertSame(0, Reserva::count());
        $this->assertSame('pendiente', $this->solicitud->fresh()->estado->value);
    }

    public function test_webhook_valido_confirma_solicitud_pendiente(): void
    {
        $response = $this->postJson(
            '/api/v1/webhooks/recaudaciones',
            $this->payload(),
            self::FIRMA_VALIDA,
        );

        $response->assertStatus(200);

        $this->solicitud->refresh();
        $this->assertSame('confirmada', $this->solicitud->estado->value);
        $this->assertSame(300.00, (float) $this->solicitud->monto_confirmado);
        $this->assertSame(2, Reserva::where('solicitud_reserva_id', $this->solicitud->id)->count());
    }

    public function test_webhook_duplicado_es_idempotente(): void
    {
        $this->postJson('/api/v1/webhooks/recaudaciones', $this->payload(), self::FIRMA_VALIDA)
            ->assertStatus(200);

        // Segunda llamada idéntica: 200 sin registros adicionales
        $this->postJson('/api/v1/webhooks/recaudaciones', $this->payload(), self::FIRMA_VALIDA)
            ->assertStatus(200);

        $this->assertSame(2, Reserva::count(), 'El webhook duplicado no debe crear reservas extra');
    }

    public function test_webhook_sobre_solicitud_expirada_no_revive_y_audita(): void
    {
        $this->solicitud->update(['estado' => EstadoSolicitudReserva::Expirada]);

        $this->postJson('/api/v1/webhooks/recaudaciones', $this->payload(), self::FIRMA_VALIDA)
            ->assertStatus(200);

        $this->assertSame('expirada', $this->solicitud->fresh()->estado->value);
        $this->assertSame(0, Reserva::count());

        $this->assertDatabaseHas('auditoria', [
            'tabla' => 'solicitudes_reserva',
            'registro_id' => $this->solicitud->id,
            'accion' => 'confirmacion_tardia_no_conciliada',
        ]);
    }

    public function test_busca_por_codigo_seguimiento_si_falla_la_referencia_del_core(): void
    {
        // El Core reenvía solo la referencia externa (doble referencia del Módulo 4)
        $this->postJson(
            '/api/v1/webhooks/recaudaciones',
            $this->payload(referenciaCore: null, referenciaExterna: 'RES-20260916-WEBH01'),
            self::FIRMA_VALIDA,
        )->assertStatus(200);

        $this->assertSame('confirmada', $this->solicitud->fresh()->estado->value);
    }

    public function test_referencia_desconocida_responde_200_sin_romper(): void
    {
        $this->postJson(
            '/api/v1/webhooks/recaudaciones',
            $this->payload(referenciaCore: 'CORE-SIM-INEXISTENTE'),
            self::FIRMA_VALIDA,
        )->assertStatus(200);

        $this->assertSame(0, Reserva::count());
    }
}

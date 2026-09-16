<?php

namespace Tests\Feature\Services;

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
use App\Services\ConfirmacionCobroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmacionCobroServiceTest extends TestCase
{
    use RefreshDatabase;

    private SolicitudReserva $solicitud;
    private ConfirmacionCobroService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ConfirmacionCobroService::class);

        // Crear un funcionario para la tarifa
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
            'precio_por_hora' => 150,
            'vigente_desde' => now(),
            'creado_por' => $admin->id,
        ]);

        // Crear una solicitud pendiente con 2 franjas
        $this->solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-20260916-TEST01',
            'monto_total' => 300.00,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'estado' => EstadoSolicitudReserva::Pendiente,
            'expira_en' => now()->addMinutes(15),
        ]);

        SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $this->solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => now()->addDay(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'tarifa_aplicada' => 150.00,
        ]);

        SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $this->solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => now()->addDay(),
            'hora_inicio' => '11:00',
            'hora_fin' => '12:00',
            'tarifa_aplicada' => 150.00,
        ]);
    }

    public function test_confirmar_pendiente_genera_reservas(): void
    {
        $this->service->confirmar($this->solicitud, 300.00);

        $this->solicitud->refresh();
        $this->assertSame('confirmada', $this->solicitud->estado->value);
        $this->assertSame(300.00, (float) $this->solicitud->monto_confirmado);

        $reservas = Reserva::where('solicitud_reserva_id', $this->solicitud->id)->get();
        $this->assertCount(2, $reservas);

        foreach ($reservas as $reserva) {
            $this->assertStringStartsWith('RSV-', $reserva->codigo_reserva);
            $this->assertSame(150.00, (float) $reserva->monto_pagado);
            $this->assertNotNull($reserva->confirmado_en);
        }
    }

    public function test_doble_llamada_es_idempotente(): void
    {
        $this->service->confirmar($this->solicitud, 300.00);
        $this->service->confirmar($this->solicitud, 300.00);

        $reservas = Reserva::where('solicitud_reserva_id', $this->solicitud->id)->get();
        $this->assertCount(2, $reservas, 'La segunda llamada no debe duplicar reservas');
    }

    public function test_confirmar_expirada_no_revive(): void
    {
        $this->solicitud->update(['estado' => EstadoSolicitudReserva::Expirada]);

        $this->service->confirmar($this->solicitud, 300.00);

        $this->solicitud->refresh();
        $this->assertSame('expirada', $this->solicitud->estado->value, 'No debe revivir');

        $reservas = Reserva::where('solicitud_reserva_id', $this->solicitud->id)->count();
        $this->assertSame(0, $reservas, 'No debe generar reservas');

        $this->assertDatabaseHas('auditoria', [
            'tabla' => 'solicitudes_reserva',
            'registro_id' => $this->solicitud->id,
            'accion' => 'confirmacion_tardia_no_conciliada',
        ]);
    }

    public function test_discrepancia_de_monto_audita_pero_confirma(): void
    {
        $this->service->confirmar($this->solicitud, 290.00); // 10 Bs menos

        $this->solicitud->refresh();
        $this->assertSame('confirmada', $this->solicitud->estado->value);
        $this->assertSame(290.00, (float) $this->solicitud->monto_confirmado);

        $this->assertDatabaseHas('auditoria', [
            'tabla' => 'solicitudes_reserva',
            'registro_id' => $this->solicitud->id,
            'accion' => 'discrepancia_monto_confirmado',
        ]);
    }
}

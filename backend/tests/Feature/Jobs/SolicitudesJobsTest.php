<?php

namespace Tests\Feature\Jobs;

use App\Enums\EstadoSolicitudReserva;
use App\Jobs\ExpirarSolicitudJob;
use App\Jobs\PollingSolicitudJob;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\HorarioAtencion;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaDetalle;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SolicitudesJobsTest extends TestCase
{
    use RefreshDatabase;

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
            'precio_por_hora' => 150,
            'vigente_desde' => now(),
            'creado_por' => $admin->id,
        ]);

        $this->solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-20260916-JOB01',
            'monto_total' => 300.00,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'referencia_recaudaciones' => 'CORE-SIM-JOB01',
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
    }

    public function test_expirar_job_marca_pendiente_vencida_como_expirada(): void
    {
        $this->solicitud->update(['expira_en' => now()->subMinute()]);

        ExpirarSolicitudJob::dispatchSync($this->solicitud->id);

        $this->assertSame('expirada', $this->solicitud->fresh()->estado->value);
        $this->assertDatabaseHas('auditoria', [
            'tabla' => 'solicitudes_reserva',
            'registro_id' => $this->solicitud->id,
            'accion' => 'expirar_automaticamente',
        ]);
    }

    public function test_expirar_job_no_toca_solicitud_ya_confirmada(): void
    {
        $this->solicitud->update(['estado' => EstadoSolicitudReserva::Confirmada]);

        ExpirarSolicitudJob::dispatchSync($this->solicitud->id);

        $this->assertSame('confirmada', $this->solicitud->fresh()->estado->value);
    }

    public function test_polling_job_confirma_si_core_responde_pagado(): void
    {
        config(['services.recaudaciones.simulado_polling_modo' => 'pagado']);

        PollingSolicitudJob::dispatchSync($this->solicitud->id);

        $this->assertSame('confirmada', $this->solicitud->fresh()->estado->value);
    }

    public function test_polling_job_se_redespacha_si_core_responde_pendiente(): void
    {
        Queue::fake();
        config(['services.recaudaciones.simulado_polling_modo' => 'pendiente']);

        PollingSolicitudJob::dispatchSync($this->solicitud->id);

        // Verificar que se re-despachó (sin ejecutarlo para evitar bucle)
        Queue::assertPushed(PollingSolicitudJob::class, function ($job) {
            return $job->solicitudId === $this->solicitud->id;
        });

        // El estado sigue pendiente (no se confirmó)
        $this->assertSame('pendiente', $this->solicitud->fresh()->estado->value);
    }

    public function test_comando_barrido_expira_solicitudes_vencidas(): void
    {
        $this->solicitud->update(['expira_en' => now()->subMinute()]);

        $this->artisan('solicitudes:expirar-vencidas')
            ->assertExitCode(0);

        $this->assertSame('expirada', $this->solicitud->fresh()->estado->value);
        $this->assertDatabaseHas('auditoria', [
            'tabla' => 'solicitudes_reserva',
            'accion' => 'expirar_por_barrido',
        ]);
    }
}

<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\HorarioAtencion;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integración con el Core de Recaudaciones vía cliente simulado (HU-D3, HU-D8).
 */
class SolicitudReservaCobroTest extends TestCase
{
    use RefreshDatabase;

    private CampoDeportivo $campo;

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

        $this->campo = CampoDeportivo::create([
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
                'campo_id' => $this->campo->id,
                'dia_semana' => $dia,
                'hora_apertura' => '08:00',
                'hora_cierre' => '20:00',
            ]);
        }

        TarifaCampo::create([
            'campo_id' => $this->campo->id,
            'precio_por_hora' => 150,
            'vigente_desde' => now(),
            'creado_por' => $admin->id,
        ]);
    }

    private function payload(): array
    {
        return [
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'franjas' => [[
                'campo_id' => $this->campo->id,
                'fecha' => Carbon::tomorrow()->toDateString(),
                'hora_inicio' => '10:00',
                'hora_fin' => '11:00',
            ]],
        ];
    }

    public function test_exito_puebla_referencia_y_devuelve_cobro(): void
    {
        config(['services.recaudaciones.simulado_modo' => 'exito']);

        $response = $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload());

        $response->assertStatus(201);
        $response->assertJsonPath('cobro.referencia_recaudaciones', fn ($v) => str_starts_with($v, 'CORE-SIM-'));
        $response->assertJsonPath('cobro.qr_string', fn ($v) => is_string($v) && $v !== '');
        $response->assertJsonPath('cobro.checkout_url', fn ($v) => is_string($v) && $v !== '');

        $solicitud = SolicitudReserva::first();
        $this->assertNotNull($solicitud->referencia_recaudaciones);
        $this->assertSame('pendiente', $solicitud->estado->value);
    }

    public function test_fallo_conexion_rechaza_libera_y_audita(): void
    {
        config(['services.recaudaciones.simulado_modo' => 'fallo_conexion']);

        $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload())
            ->assertStatus(503);

        $solicitud = SolicitudReserva::first();
        $this->assertSame('rechazada', $solicitud->estado->value, 'HU-D8: rechazo inmediato, no a los 15 min');

        $this->assertDatabaseHas('auditoria', ['accion' => 'fallo_conexion_core']);

        // La franja quedó liberada al acto: una nueva solicitud pasa sin chocar
        config(['services.recaudaciones.simulado_modo' => 'exito']);
        $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload())
            ->assertStatus(201);

        $this->assertSame(2, SolicitudReserva::count());
    }

    public function test_fallo_conexion_responde_503_no_500(): void
    {
        config(['services.recaudaciones.simulado_modo' => 'fallo_conexion']);

        $response = $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload());

        $response->assertStatus(503);
        $response->assertJsonPath('error', 'servicio_cobro_no_disponible');
    }
}

<?php

namespace Tests\Feature\Api\V1\Public;

use App\Enums\EstadoSolicitudReserva;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\HorarioAtencion;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaDetalle;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatosCobroPendienteTest extends TestCase
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
            'codigo_seguimiento' => 'RES-20260916-COB01',
            'monto_total' => 300.00,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'referencia_recaudaciones' => 'CORE-SIM-COB01',
            'datos_cobro_pendiente' => [
                'qr_string' => 'https://core.gob.bo/pago/sim/RES-20260916-COB01',
                'qr_image_base64' => null,
                'checkout_url' => 'https://core.gob.bo/checkout/sim/RES-20260916-COB01',
            ],
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

    public function test_endpoint_estado_incluye_datos_cobro_cuando_pendiente(): void
    {
        $response = $this->getJson('/api/v1/public/solicitudes-reserva/RES-20260916-COB01/estado');

        $response->assertStatus(200);
        $response->assertJsonPath('data.estado', 'pendiente');
        $response->assertJsonPath('data.datos_cobro_pendiente.qr_string', 'https://core.gob.bo/pago/sim/RES-20260916-COB01');
        $response->assertJsonPath('data.datos_cobro_pendiente.checkout_url', 'https://core.gob.bo/checkout/sim/RES-20260916-COB01');
    }

    public function test_endpoint_estado_no_incluye_datos_cobro_cuando_confirmada(): void
    {
        $this->solicitud->update(['estado' => EstadoSolicitudReserva::Confirmada]);

        $response = $this->getJson('/api/v1/public/solicitudes-reserva/RES-20260916-COB01/estado');

        $response->assertStatus(200);
        $response->assertJsonPath('data.estado', 'confirmada');
        $response->assertJsonMissingPath('data.datos_cobro_pendiente');
    }

    public function test_endpoint_estado_no_incluye_datos_cobro_cuando_expirada(): void
    {
        $this->solicitud->update(['estado' => EstadoSolicitudReserva::Expirada]);

        $response = $this->getJson('/api/v1/public/solicitudes-reserva/RES-20260916-COB01/estado');

        $response->assertStatus(200);
        $response->assertJsonPath('data.estado', 'expirada');
        $response->assertJsonMissingPath('data.datos_cobro_pendiente');
    }
}

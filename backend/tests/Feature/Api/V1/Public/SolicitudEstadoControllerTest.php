<?php

namespace Tests\Feature\Api\V1\Public;

use App\Enums\EstadoSolicitudReserva;
use App\Models\CampoDeportivo;
use App\Models\Reserva;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaDetalle;
use App\Models\TipoCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudEstadoControllerTest extends TestCase
{
    use RefreshDatabase;

    private SolicitudReserva $solicitud;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->solicitud = SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-20260916-EST01',
            'monto_total' => 300.00,
            'nombre_pagador' => 'Juan Pérez',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'referencia_recaudaciones' => 'CORE-SIM-EST01',
            'estado' => EstadoSolicitudReserva::Pendiente,
            'expira_en' => now()->addMinutes(15),
        ]);

        $detalle = SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $this->solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => now()->addDay(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'tarifa_aplicada' => 150.00,
        ]);
    }

    public function test_consulta_pendiente_devuelve_estado_sin_datos_sensibles(): void
    {
        $response = $this->getJson('/api/v1/public/solicitudes-reserva/RES-20260916-EST01/estado');

        $response->assertStatus(200);
        $response->assertJsonPath('data.estado', 'pendiente');
        $response->assertJsonPath('data.monto_total', 300);
        $response->assertJsonMissingPath('data.nombre_pagador');
        $response->assertJsonMissingPath('data.telefono_pagador');
        $response->assertJsonMissingPath('data.referencia_recaudaciones');
        $response->assertJsonMissingPath('data.reservas');
    }

    public function test_consulta_confirmada_incluye_reservas(): void
    {
        $this->solicitud->update(['estado' => EstadoSolicitudReserva::Confirmada]);

        Reserva::create([
            'solicitud_reserva_id' => $this->solicitud->id,
            'solicitud_reserva_detalle_id' => $this->solicitud->detalles()->first()->id,
            'codigo_reserva' => 'RSV-20260916-ABC123',
            'campo_id' => $this->solicitud->detalles()->first()->campo_id,
            'fecha_reserva' => now()->addDay(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'monto_pagado' => 150.00,
            'confirmado_en' => now(),
        ]);

        $response = $this->getJson('/api/v1/public/solicitudes-reserva/RES-20260916-EST01/estado');

        $response->assertStatus(200);
        $response->assertJsonPath('data.estado', 'confirmada');
        $response->assertJsonCount(1, 'data.reservas');
        $response->assertJsonPath('data.reservas.0.codigo_reserva', 'RSV-20260916-ABC123');
    }

    public function test_codigo_inexistente_responde_404(): void
    {
        $response = $this->getJson('/api/v1/public/solicitudes-reserva/RES-INEXISTENTE/estado');

        $response->assertStatus(404);
    }
}

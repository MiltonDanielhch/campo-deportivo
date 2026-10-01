<?php

namespace Tests\Feature\Api\V1\Public;

use App\Jobs\ReintentarSolicitudJob;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use App\Models\HorarioAtencion;
use App\Models\Rol;
use App\Models\SolicitudReserva;
use App\Models\TarifaCampo;
use App\Models\TipoCampo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Integración con SIREB vía cliente simulado (HU-D3, HU-D8 adaptado a SIREB v1).
 *
 * Diseño nuevo: si SIREB falla al crear la liquidación, la solicitud NO se
 * rechaza de inmediato — queda 'pendiente' y se despacha ReintentarSolicitudJob
 * (5 intentos × 60s). La franja sigue ocupada mientras esté pendiente.
 */
class SolicitudReservaCobroTest extends TestCase
{
    use RefreshDatabase;

    private CampoDeportivo $campo;

    protected function setUp(): void
    {
        parent::setUp();

        // Cola fake: los jobs (Expirar/Polling/Reintentar) NO se ejecutan inline.
        // Así la solicitud queda 'pendiente' y los asserts de estado son fiables.
        Queue::fake();

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
            // UUID del catálogo fake del simulador (SERVICIO_ID)
            'servicio_sireb_id' => 'aaaaaaaa-0000-4000-8000-000000000001',
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
            'tipo_tarifa' => 'diurna',
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
            'ci_nit_pagador' => '1234567', // requerido por el flujo SIREB (buscarCliente)
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
        // SIREB v1: referencia = código público (SIM-... en simulado)
        $response->assertJsonPath('cobro.referencia_recaudaciones', fn ($v) => is_string($v) && str_starts_with($v, 'SIM-'));
        // El QR se arma localmente con el código público
        $response->assertJsonPath('cobro.qr_string', fn ($v) => is_string($v) && str_starts_with($v, 'SIREB:'));
        // SIREB v1 no tiene checkout electrónico
        $response->assertJsonPath('cobro.checkout_url', null);

        $solicitud = SolicitudReserva::first();
        $this->assertNotNull($solicitud->referencia_recaudaciones);
        $this->assertNotNull($solicitud->liquidacion_id, 'Debe guardar el id de SIREB para poder anular después');
        $this->assertSame('pendiente', $solicitud->estado->value);

        // Éxito → no se despachó job de reintento
        Queue::assertPushed(ReintentarSolicitudJob::class, 0);
    }

    public function test_fallo_conexion_despacha_reintento_y_queda_pendiente(): void
    {
        config(['services.recaudaciones.simulado_modo' => 'fallo_conexion']);

        // Diseño nuevo: NO se rechaza de inmediato ni se responde 503.
        // La solicitud queda pendiente y el reintento va en background.
        $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload())
            ->assertStatus(201);

        $solicitud = SolicitudReserva::first();
        $this->assertSame('pendiente', $solicitud->estado->value);
        $this->assertNull($solicitud->referencia_recaudaciones, 'Sin liquidación todavía');
        $this->assertNull($solicitud->liquidacion_id);

        Queue::assertPushed(ReintentarSolicitudJob::class, 1);

        // La franja sigue ocupada por la solicitud pendiente (no se libera al acto):
        // una segunda solicitud de la misma franja choca con el EXCLUDE → 409.
        config(['services.recaudaciones.simulado_modo' => 'exito']);
        $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload())
            ->assertStatus(409);

        $this->assertSame(1, SolicitudReserva::count(), 'La segunda solicitud no debe crearse');
    }

    public function test_fallo_conexion_no_revienta_500(): void
    {
        config(['services.recaudaciones.simulado_modo' => 'fallo_conexion']);

        // El espíritu del test original (no 500) se conserva: la caída de SIREB
        // no rompe la request — responde 201 con la solicitud pendiente.
        $response = $this->postJson('/api/v1/public/solicitudes-reserva', $this->payload());

        $response->assertStatus(201);
        $response->assertJsonMissingPath('error');
    }
}

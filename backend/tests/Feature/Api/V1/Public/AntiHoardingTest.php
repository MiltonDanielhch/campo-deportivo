<?php

namespace Tests\Feature\Api\V1\Public;

use App\Enums\EstadoSolicitudReserva;
use App\Models\CampoDeportivo;
use App\Models\HorarioAtencion;
use App\Models\ParametroSistema;
use App\Models\TipoCampo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AntiHoardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedParametros();

        // Mockear CatalogoSirebService para que los tests de gates no fallen en SIREB
        $this->mock(\App\Services\CatalogoSirebService::class, function ($mock) {
            $mock->shouldReceive('resolverTarifaId')
                ->andReturn('tarifa-fake-uuid-' . uniqid());
        });
    }

    public function test_m1_dinamico_rechaza_mas_franjas_del_tope(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 09:00:00'));
        $campo = $this->crearCampoConHorario();

        // Pedir 7 franjas cuando el parámetro está en 6
        $franjas = [];
        for ($h = 10; $h < 17; $h++) {
            $franjas[] = [
                'campo_id' => $campo->id,
                'fecha' => '2026-10-14',
                'hora_inicio' => sprintf('%02d:00:00', $h),
                'hora_fin' => sprintf('%02d:00:00', $h + 1),
            ];
        }

        $response = $this->postJson('/api/v1/public/solicitudes-reserva', [
            'nombre_pagador' => 'Test',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'franjas' => $franjas,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.franjas.0', 'No podés reservar más de 6 franjas en una sola solicitud.');
    }

    public function test_m2_rechaza_cuando_ip_tiene_demasiadas_pendientes(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 09:00:00'));

        $campo = $this->crearCampoConHorario();
        $ip = '203.0.113.42';

        // Crear 3 solicitudes pendientes activas desde la misma IP (tope = 3)
        for ($i = 0; $i < 3; $i++) {
            $this->crearSolicitudPendiente(
                $campo,
                '2026-10-14',
                '1' . (4 + $i) . ':00:00',
                '1' . (5 + $i) . ':00:00',
                $ip
            );
        }

        // Diagnóstico 1: confirmar que las 3 pendientes sí quedaron guardadas con la IP esperada
        $pendientesConIp = \App\Models\SolicitudReserva::query()
            ->where('ip_cobrador', $ip)
            ->where('estado', EstadoSolicitudReserva::Pendiente->value)
            ->where('expira_en', '>', now())
            ->count();

        $this->assertSame(
            3,
            $pendientesConIp,
            'Las 3 solicitudes pendientes no quedaron guardadas con la IP esperada.'
        );

        // Diagnóstico 2: hacer el request forzando REMOTE_ADDR y X-Forwarded-For
        $response = $this
            ->withoutMiddleware([\Illuminate\Routing\Middleware\ThrottleRequests::class])
            ->withServerVariables([
                'REMOTE_ADDR' => $ip,
                'HTTP_X_FORWARDED_FOR' => $ip,
            ])
            ->postJson('/api/v1/public/solicitudes-reserva', [
                'nombre_pagador' => 'Hoarding User',
                'telefono_pagador' => '70000000',
                'ci_nit_pagador' => '7654321',
                'franjas' => [[
                    'campo_id' => $campo->id,
                    'fecha' => '2026-10-14',
                    'hora_inicio' => '18:00:00',
                    'hora_fin' => '19:00:00',
                ]],
            ]);

        // Si pasó de largo, te dice exactamente qué IP se guardó en la solicitud nueva
        if ($response->status() === 201) {
            $nueva = \App\Models\SolicitudReserva::query()
                ->where('nombre_pagador', 'Hoarding User')
                ->latest('creado_en')
                ->first();

            $this->fail(sprintf(
                "M2 no bloqueó la solicitud.\nIP esperada: %s\nIP guardada en la solicitud nueva: %s\nPendientes contadas antes del request: %d",
                $ip,
                var_export($nueva?->ip_cobrador, true),
                $pendientesConIp
            ));
        }

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.franjas.0',
                'Tenés 3 reservas pendientes por pagar. Confirmá o esperá a que expiren antes de crear una nueva.'
            );
    }

    public function test_m2_no_cuenta_expiradas_ni_confirmadas(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 09:00:00'));
        $campo = $this->crearCampoConHorario();
        $ip = '203.0.113.99';

        // 2 expiradas + 1 confirmada + 1 pendiente = debería contar solo 1
        $this->crearSolicitudConEstado($campo, $ip, EstadoSolicitudReserva::Expirada,   '2026-10-14', '10:00:00', '11:00:00');
        $this->crearSolicitudConEstado($campo, $ip, EstadoSolicitudReserva::Expirada,   '2026-10-14', '11:00:00', '12:00:00');
        $this->crearSolicitudConEstado($campo, $ip, EstadoSolicitudReserva::Confirmada, '2026-10-14', '12:00:00', '13:00:00');
        $this->crearSolicitudConEstado($campo, $ip, EstadoSolicitudReserva::Pendiente,  '2026-10-14', '13:00:00', '14:00:00');

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/public/solicitudes-reserva', [
                'nombre_pagador' => 'User Legit',
                'telefono_pagador' => '70000000',
                'ci_nit_pagador' => '1111111',
                'franjas' => [[
                    'campo_id' => $campo->id,
                    'fecha' => '2026-10-14',
                    'hora_inicio' => '18:00:00',
                    'hora_fin' => '19:00:00',
                ]],
            ]);

        // No debe fallar por M2 (1 pendiente < 3 tope); si falla será por SIREB u otro motivo, no 422 en franjas
        $response->assertStatus(201);
    }

    public function test_m3_ip_en_cooldown_recibe_422(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 09:00:00'));
        $campo = $this->crearCampoConHorario();
        $ip = '203.0.113.77';

        // Simular que la IP ya está en cooldown
        Cache::put('hoard:cooldown:'.md5($ip), 15, now()->addMinutes(15));

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/public/solicitudes-reserva', [
                'nombre_pagador' => 'Bloqueado',
                'telefono_pagador' => '70000000',
                'ci_nit_pagador' => '2222222',
                'franjas' => [[
                    'campo_id' => $campo->id,
                    'fecha' => '2026-10-14',
                    'hora_inicio' => '18:00:00',
                    'hora_fin' => '19:00:00',
                ]],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.franjas.0', 'Esta IP fue bloqueada temporalmente por reservas sin pagar. Intentá nuevamente en 15 minutos.');
    }

    public function test_m3_expirar_job_incrementa_contador_y_dispara_cooldown(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 09:00:00'));
        $campo = $this->crearCampoConHorario();
        $ip = '203.0.113.55';

        // 2 expiraciones previas en la última hora (contador = 2)
        Cache::put('hoard:counter:'.md5($ip), 2, now()->addHour());

        // Solicitud pendiente a punto de expirar
        $solicitud = $this->crearSolicitudConEstado($campo, $ip, EstadoSolicitudReserva::Pendiente);
        $solicitud->update(['expira_en' => now()->subMinute()]);

        // ← CAMBIO: ejecutar el handle() directamente en lugar de dispatch_sync
        $job = new \App\Jobs\ExpirarSolicitudJob($solicitud->id);
        $client = app(\App\Integrations\Recaudaciones\RecaudacionesApiClientInterface::class);
        $job->handle($client);

        // Contador pasó a 3 (umbral) → cooldown activado
        $this->assertEquals(3, Cache::get('hoard:counter:'.md5($ip)));
        $this->assertTrue(Cache::has('hoard:cooldown:'.md5($ip)));
        $this->assertEquals(30, Cache::get('hoard:cooldown:'.md5($ip)));
    }

    public function test_usuario_legitimo_no_se_ve_afectado(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 09:00:00'));
        $campo = $this->crearCampoConHorario();

        $response = $this->postJson('/api/v1/public/solicitudes-reserva', [
            'nombre_pagador' => 'Ciudadano Normal',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '9876543',
            'franjas' => [[
                'campo_id' => $campo->id,
                'fecha' => '2026-10-14',
                'hora_inicio' => '18:00:00',
                'hora_fin' => '19:00:00',
            ]],
        ]);

        // Pasa todos los gates: 1 franja ≤ 6, 0 pendientes < 3, sin cooldown
        $response->assertStatus(201);
    }

    // ─────────────────────── Helpers ───────────────────────

    private function seedParametros(): void
    {
        foreach ([
            ['max_franjas_por_solicitud', '6', ''],
            ['max_pendientes_por_ip', '3', ''],
            ['umbral_expiraciones_cooldown', '3', ''],
            ['cooldown_expiracion_minutos', '30', ''],
            ['polling_intervalo_segundos', '20', ''],
            ['solicitud_reserva_expiracion_minutos', '15', ''],
        ] as [$clave, $valor, $desc]) {
            ParametroSistema::updateOrCreate(
                ['clave' => $clave],
                ['valor' => $valor, 'descripcion' => $desc],
            );
        }
    }

    private function crearCampoConHorario(): CampoDeportivo
    {
        $tipo = TipoCampo::query()->firstOrCreate(
            ['nombre' => 'Futsal'],
            ['descripcion' => 'Tipo de prueba', 'estado' => 'activo']
        );

        $campo = CampoDeportivo::create([
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'CAMPO-'.uniqid(),
            'nombre' => 'Cancha Test',
            'direccion' => 'Dirección',
            'latitud' => -14.83,
            'longitud' => -64.90,
            'estado' => 'activo',
            'hora_inicio_noche' => '18:00:00',
            'servicio_sireb_id' => '11111111-1111-4111-8111-111111111111',  // ← NUEVO
            'servicio_sireb_codigo' => 'SEDEDE-TEST',  // ← NUEVO
        ]);

        for ($dia = 1; $dia <= 7; $dia++) {
            HorarioAtencion::create([
                'campo_id' => $campo->id,
                'dia_semana' => $dia,
                'hora_apertura' => '08:00:00',
                'hora_cierre' => '22:00:00',
            ]);
        }

        \App\Models\TarifaCampo::create([
            'campo_id' => $campo->id,
            'tipo_tarifa' => 'diurna',
            'precio_por_hora' => 100.00,
            'vigente_desde' => now()->subDay(),
            'vigente_hasta' => null,
        ]);
        \App\Models\TarifaCampo::create([
            'campo_id' => $campo->id,
            'tipo_tarifa' => 'nocturna',
            'precio_por_hora' => 150.00,
            'vigente_desde' => now()->subDay(),
            'vigente_hasta' => null,
        ]);

        return $campo;
    }

    private function crearSolicitudPendiente(
        CampoDeportivo $campo,
        string $fecha,
        string $hi,
        string $hf,
        string $ip
    ): void {
        $this->crearSolicitudConEstado($campo, $ip, EstadoSolicitudReserva::Pendiente, $fecha, $hi, $hf);
    }

        private function crearSolicitudConEstado(
            CampoDeportivo $campo,
            string $ip,
            EstadoSolicitudReserva $estado,
            string $fecha = '2026-10-14',
            string $hi = '18:00:00',
            string $hf = '19:00:00',
        ) {
        $solicitud = \App\Models\SolicitudReserva::create([
            'codigo_seguimiento' => 'RES-'.uniqid(),
            'monto_total' => 100,
            'monto_confirmado' => $estado === EstadoSolicitudReserva::Confirmada ? 100 : 0,
            'nombre_pagador' => 'Test',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'ip_cobrador' => $ip,
            'estado' => $estado,
            'expira_en' => now()->addMinutes(15),
        ]);

        $detalle = \App\Models\SolicitudReservaDetalle::create([
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => $fecha,
            'hora_inicio' => $hi,
            'hora_fin' => $hf,
            'tarifa_aplicada' => 100,
        ]);
        $detalle->forceFill(['estado_solicitud' => $estado->value])->save();

        if ($estado === EstadoSolicitudReserva::Confirmada) {
            \App\Models\Reserva::create([
                'solicitud_reserva_id' => $solicitud->id,
                'solicitud_reserva_detalle_id' => $detalle->id,
                'codigo_reserva' => 'RSV-'.uniqid(),
                'campo_id' => $campo->id,
                'fecha_reserva' => $fecha,
                'hora_inicio' => $hi,
                'hora_fin' => $hf,
                'monto_pagado' => 100,
                'confirmado_en' => now(),
            ]);
        }

        return $solicitud;
    }
}

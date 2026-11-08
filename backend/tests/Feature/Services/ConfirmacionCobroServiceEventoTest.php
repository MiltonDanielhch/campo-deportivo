<?php

namespace Tests\Feature\Services;

use App\Enums\EstadoSolicitudReserva;
use App\Events\SolicitudConfirmada;
use App\Models\CampoDeportivo;
use App\Models\SolicitudReserva;
use App\Models\SolicitudReservaDetalle;
use App\Models\TipoCampo;
use App\Services\ConfirmacionCobroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConfirmacionCobroServiceEventoTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispara_evento_al_confirmar(): void
    {
        Event::fake([SolicitudConfirmada::class]);

        // Crear tipo de campo manualmente
        $tipo = TipoCampo::create([
            'id' => Str::uuid()->toString(),
            'nombre' => 'Cancha de prueba',
            'descripcion' => 'Descripción de prueba',
            'estado' => 'activo',
        ]);

        // Crear campo deportivo manualmente
        $campo = CampoDeportivo::create([
            'id' => Str::uuid()->toString(),
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'TEST-001',
            'nombre' => 'Campo de prueba',
            'direccion' => 'Dirección de prueba',
            'latitud' => -14.8,
            'longitud' => -64.9,
            'estado' => 'activo',
            'hora_inicio_noche' => '18:00:00',
        ]);

        $solicitud = SolicitudReserva::create([
            'id' => Str::uuid()->toString(),
            'codigo_seguimiento' => 'RES-TEST-EVENTO',
            'monto_total' => 50,
            'nombre_pagador' => 'Test',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'estado' => EstadoSolicitudReserva::Pendiente,
            'creado_en' => now(),
            'expira_en' => now()->addMinutes(15),
        ]);

        SolicitudReservaDetalle::create([
            'id' => Str::uuid()->toString(),
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'tarifa_aplicada' => 50,
        ]);

        $service = app(ConfirmacionCobroService::class);
        $service->confirmar($solicitud, 50.0);

        Event::assertDispatched(SolicitudConfirmada::class, function ($event) use ($solicitud) {
            return $event->solicitud->id === $solicitud->id;
        });
    }

    public function test_no_dispara_evento_si_ya_estaba_confirmada(): void
    {
        Event::fake([SolicitudConfirmada::class]);

        $solicitud = SolicitudReserva::create([
            'id' => Str::uuid()->toString(),
            'codigo_seguimiento' => 'RES-YA-CONF',
            'monto_total' => 50,
            'nombre_pagador' => 'Test',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'estado' => EstadoSolicitudReserva::Confirmada,
            'creado_en' => now(),
            'expira_en' => now()->addMinutes(15),
        ]);

        $service = app(ConfirmacionCobroService::class);
        $service->confirmar($solicitud, 50.0);

        Event::assertNotDispatched(SolicitudConfirmada::class);
    }

    /**
     * [Gap2-fix] Un listener que FALLA no revierte la confirmación ni se
     * confunde con idempotencia 23505, y no propaga (no 500 -> no retry que
     * pierda el efecto). La solicitud queda Confirmada y el fallo se loggea
     * best-effort. Prueba los dos sub-bugs a la vez: sub-bug 1 (el error del
     * listener ya no cae en el catch 23505 del DB::transaction) y sub-bug 2
     * (no propaga, así el webhook da 200 y el retry del Core no early-returna
     * perdiendo el evento).
     *
     * Usa Event::listen REAL (no fake) para que el listener corra y lance.
     * Si el grep de SolicitudConfirmada mostrara un listener de producción,
     * este test también lo dispararía; por eso el grep es el pre-check del fix.
     */
    public function test_listener_que_falla_no_rompe_la_confirmacion(): void
    {
        Log::spy();

        Event::listen(SolicitudConfirmada::class, function () {
            throw new \RuntimeException('listener de notificación cae a propósito');
        });

        $tipo = TipoCampo::create([
            'id' => Str::uuid()->toString(),
            'nombre' => 'Cancha de prueba',
            'descripcion' => 'Descripción de prueba',
            'estado' => 'activo',
        ]);

        $campo = CampoDeportivo::create([
            'id' => Str::uuid()->toString(),
            'tipo_campo_id' => $tipo->id,
            'codigo' => 'TEST-LST-001',
            'nombre' => 'Campo de prueba',
            'direccion' => 'Dirección de prueba',
            'latitud' => -14.8,
            'longitud' => -64.9,
            'estado' => 'activo',
            'hora_inicio_noche' => '18:00:00',
        ]);

        $solicitud = SolicitudReserva::create([
            'id' => Str::uuid()->toString(),
            'codigo_seguimiento' => 'RES-TEST-LST',
            'monto_total' => 50,
            'nombre_pagador' => 'Test',
            'telefono_pagador' => '70000000',
            'ci_nit_pagador' => '1234567',
            'estado' => EstadoSolicitudReserva::Pendiente,
            'creado_en' => now(),
            'expira_en' => now()->addMinutes(15),
        ]);

        SolicitudReservaDetalle::create([
            'id' => Str::uuid()->toString(),
            'solicitud_reserva_id' => $solicitud->id,
            'campo_id' => $campo->id,
            'fecha_reserva' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'tarifa_aplicada' => 50,
        ]);

        $service = app(ConfirmacionCobroService::class);

        // NO debe lanzar: el try best-effort del event captura el fallo.
        $service->confirmar($solicitud, 50.0);

        // La confirmación quedó intacta (tx commiteó antes del event).
        $this->assertSame('confirmada', $solicitud->fresh()->estado->value);

        // Y el fallo se registró best-effort (no se tragó en silencio).
        Log::shouldHaveReceived('warning')->once();
    }
}

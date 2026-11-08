<?php

namespace App\Services;

use App\Enums\EstadoSolicitudReserva;
use App\Models\Reserva;
use App\Models\SolicitudReserva;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\SolicitudConfirmada;

/**
 * Servicio compartido de confirmación de cobro.
 *
 * Lo llaman tanto el webhook del Core (HU-D4) como el polling opcional (HU-D5),
 * garantizando que la lógica nunca se desincronice entre los dos caminos.
 *
 * La idempotencia se garantiza con la restricción UNIQUE sobre
 * reservas.solicitud_reserva_detalle_id (Anexo A.2 del Documento 2 v3).
 */
class ConfirmacionCobroService
{
    private const ALFABETO_CODIGO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function confirmar(SolicitudReserva $solicitud, ?float $montoConfirmado = null): void
    {
        // Idempotencia: si ya está confirmada, no hay nada más que hacer
        if ($solicitud->estado === EstadoSolicitudReserva::Confirmada) {
            return;
        }

        // Regla de confirmación tardía: si ya expiró/canceló/rechazó, NO revivir
        if ($solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            $this->registrarConfirmacionTardia($solicitud, $montoConfirmado);
            return;
        }

        // Detectar discrepancia de monto (no bloquea, solo audita)
        if ($montoConfirmado !== null && abs($montoConfirmado - (float) $solicitud->monto_total) > 0.01) {
            AuditoriaService::registrar(
                'solicitudes_reserva',
                $solicitud->id,
                'discrepancia_monto_confirmado',
                null,
                ['monto_total' => $solicitud->monto_total],
                ['monto_confirmado' => $montoConfirmado]
            );
        }

        // [Gap2-fix] El catch 23505 envuelve SOLO el DB::transaction. Antes
        // envolvía también el event(), así que una QueryException 23505 que
        // viniera del LISTENER se tragaba como si fuera idempotencia de
        // Reserva::create (sub-bug 1): la tx ya commiteó, la solicitud quedó
        // confirmada y el efecto del listener se perdió en silencio. Con el
        // catch angostado, 23505 de Reserva::create sigue siendo idempotencia
        // (retry del webhook sobre una confirmación previa), y cualquier error
        // del listener cae en el try best-effort de abajo, no acá.
        try {
            DB::transaction(function () use ($solicitud, $montoConfirmado) {
                $solicitud->update([
                    'estado' => EstadoSolicitudReserva::Confirmada,
                    'monto_confirmado' => $montoConfirmado,
                ]);

                foreach ($solicitud->detalles as $detalle) {
                    Reserva::create([
                        'solicitud_reserva_id' => $solicitud->id,
                        'solicitud_reserva_detalle_id' => $detalle->id,
                        'codigo_reserva' => $this->generarCodigoReserva(),
                        'campo_id' => $detalle->campo_id,
                        'fecha_reserva' => $detalle->fecha_reserva,
                        'hora_inicio' => $detalle->hora_inicio,
                        'hora_fin' => $detalle->hora_fin,
                        'monto_pagado' => $detalle->tarifa_aplicada,
                        'confirmado_en' => now(),
                    ]);
                }
            });
        } catch (QueryException $e) {
            // 23505 = unique_violation: ya se procesó (idempotencia por restricción de BD)
            if ($e->getCode() === '23505') {
                return;
            }
            throw $e;
        }

        // [Gap2-fix] El evento de confirmación es BEST-EFFORT (docblock:
        // "Preparación para Módulo 10 — notificaciones email/SMS"). Va FUERA
        // del catch 23505 y en su propio try que LOGGEA y NO propaga. Esto
        // cierra el sub-bug 2 (lost-update en retry): si el listener lanza,
        // antes propagaba -> webhook 500 -> Core reintenta -> confirmar()
        // early-returna por estado===Confirmada -> el evento NUNCA se
        // re-disparaba. Ahora el fallo se registra y la confirmación (ya
        // commiteada) queda intacta; el webhook devuelve 200 y no hay retry
        // que perder el efecto. Una notificación perdida se reconcilia por
        // log, no revirtiendo una reserva pagada. Si el Módulo 10 pusiera un
        // listener CRÍTICO (no notificación), este try best-effort sería
        // incorrecto y habría que garantizar re-disparo (marker/migration) —
        // el grep de SolicitudConfirmada en app/ es lo que descarta ese caso.
        try {
            event(new SolicitudConfirmada($solicitud));
        } catch (\Throwable $e) {
            Log::warning(
                'Listener de SolicitudConfirmada falló; confirmación intacta',
                [
                    'solicitud_id' => $solicitud->id,
                    'codigo_seguimiento' => $solicitud->codigo_seguimiento,
                    'error' => $e->getMessage(),
                ],
            );
        }
    }

    /**
     * Registra una confirmación que llegó después de que la solicitud ya
     * había expirado/cancelado/rechazado. No revive la solicitud, solo
     * deja el rastro para que el administrador gestione por fuera.
     */
    private function registrarConfirmacionTardia(
        SolicitudReserva $solicitud,
        ?float $montoConfirmado
    ): void {
        AuditoriaService::registrar(
            'solicitudes_reserva',
            $solicitud->id,
            'confirmacion_tardia_no_conciliada',
            null,
            ['estado' => $solicitud->estado->value],
            ['monto_confirmado' => $montoConfirmado]
        );
    }

    /**
     * Genera un código de reserva único: RSV-YYYYMMDD-XXXXXX
     * (alfabeto sin caracteres ambiguos, con reintento en caso de colisión).
     */
    private function generarCodigoReserva(): string
    {
        do {
            $aleatorio = '';
            for ($i = 0; $i < 6; $i++) {
                $aleatorio .= self::ALFABETO_CODIGO[random_int(0, strlen(self::ALFABETO_CODIGO) - 1)];
            }
            $codigo = 'RSV-' . now()->format('Ymd') . '-' . $aleatorio;
        } while (Reserva::where('codigo_reserva', $codigo)->exists());

        return $codigo;
    }
}

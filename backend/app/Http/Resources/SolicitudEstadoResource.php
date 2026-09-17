<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estado público de una solicitud de reserva (HU-D7).
 * NO expone: nombre_pagador, telefono_pagador, referencia_recaudaciones
 * (son datos internos o del solicitante, no públicos).
 *
 * datos_cobro_pendiente (Fase WP.0) solo viaja mientras el estado es
 * 'pendiente': es lo que permite reabrir el link de pago y ver el mismo
 * QR/checkout. Una vez resuelta la solicitud, ya no tiene sentido.
 */
class SolicitudEstadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'codigo_seguimiento' => $this->codigo_seguimiento,
            'estado' => $this->estado->value,
            'monto_total' => (float) $this->monto_total,
            'expira_en' => $this->expira_en?->toIso8601String(),
            'datos_cobro_pendiente' => $this->when(
                $this->estado->value === 'pendiente',
                fn () => $this->datos_cobro_pendiente,
            ),
            'reservas' => $this->when(
                $this->estado->value === 'confirmada',
                fn () => $this->reservas->map(fn ($reserva) => [
                    'codigo_reserva' => $reserva->codigo_reserva,
                    'campo_nombre' => $reserva->campo->nombre,
                    'fecha' => $reserva->fecha_reserva->format('Y-m-d'),
                    'hora_inicio' => substr($reserva->hora_inicio, 0, 5),
                    'hora_fin' => substr($reserva->hora_fin, 0, 5),
                    'confirmado_en' => $reserva->confirmado_en?->toIso8601String(), // NUEVO
                ]),
            ),
        ];
    }
}

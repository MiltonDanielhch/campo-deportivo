<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource administrativo para listado y detalle de solicitudes.
 *
 * A diferencia del SolicitudEstadoResource público, este resource expone
 * datos de contacto y referencia SIREB porque el GAD necesita operar,
 * auditar y contactar al pagador.
 *
 * Importante:
 * - solicitudes_reserva NO tiene columna confirmado_en.
 * - La confirmación vive en reservas.confirmado_en.
 * - Por eso el confirmado_en del resource se deriva de la primera reserva.
 */
class SolicitudReservaAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_seguimiento' => $this->codigo_seguimiento,
            'estado' => $this->estado->value,

            'monto_total' => (float) $this->monto_total,
            'monto_confirmado' => $this->monto_confirmado === null
                ? null
                : (float) $this->monto_confirmado,

            'nombre_pagador' => $this->nombre_pagador,
            'telefono_pagador' => $this->telefono_pagador,
            'ci_nit_pagador' => $this->ci_nit_pagador,

            'referencia_recaudaciones' => $this->referencia_recaudaciones,
            'motivo_rechazo' => $this->motivo_rechazo,

            'creado_en' => $this->creado_en?->toIso8601String(),
            'expira_en' => $this->expira_en?->toIso8601String(),

            // Derivado desde reservas, porque solicitudes_reserva no tiene confirmado_en.
            'confirmado_en' => $this->whenLoaded(
                'detalles',
                fn () => $this->detalles
                    ->pluck('reserva.confirmado_en')
                    ->filter()
                    ->first()
                    ?->toIso8601String()
            ),

            'detalles' => $this->whenLoaded(
                'detalles',
                fn () => $this->detalles
                    ->map(fn ($detalle) => [
                        'id' => $detalle->id,
                        'campo_id' => $detalle->campo_id,
                        'campo_nombre' => $detalle->campo?->nombre,
                        'fecha_reserva' => $detalle->fecha_reserva?->toDateString(),
                        'hora_inicio' => substr((string) $detalle->hora_inicio, 0, 5),
                        'hora_fin' => substr((string) $detalle->hora_fin, 0, 5),
                        'tarifa_aplicada' => (float) $detalle->tarifa_aplicada,
                        'reserva' => $detalle->reserva ? [
                            'id' => $detalle->reserva->id,
                            'codigo_reserva' => $detalle->reserva->codigo_reserva,
                            'confirmado_en' => $detalle->reserva->confirmado_en?->toIso8601String(),
                            'asistencia_marcada_en' => $detalle->reserva->asistencia_marcada_en?->toIso8601String(),
                        ] : null,
                    ])
                    ->values()
                    ->all()
            ),
        ];
    }
}

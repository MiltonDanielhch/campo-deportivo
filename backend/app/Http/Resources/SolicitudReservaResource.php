<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Respuesta 201 de una solicitud creada.
 * Incluye lo que la app necesita para la pantalla de cobro:
 * codigo_seguimiento visible, monto definitivo y expira_en
 * (la cuenta regresiva se calcula contra este valor, nunca
 * contra el reloj del dispositivo).
 *
 * El bloque 'cobro' (referencia del Core, QR, checkoutUrl) se
 * agrega con ->additional() desde el controller: en la Fase 4.1
 * viaja null y en la Fase 4.2 se pobla con la respuesta del Core,
 * sin romper el contrato.
 */
class SolicitudReservaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_seguimiento' => $this->codigo_seguimiento,
            'monto_total' => (float) $this->monto_total,
            'estado' => $this->estado->value,
            'expira_en' => $this->expira_en->toIso8601String(),
            'franjas' => $this->whenLoaded('detalles', fn () => $this->detalles->map(
                fn ($detalle) => [
                    'campo_id' => $detalle->campo_id,
                    'fecha' => $detalle->fecha_reserva->toDateString(),
                    'hora_inicio' => substr($detalle->hora_inicio, 0, 5),
                    'hora_fin' => substr($detalle->hora_fin, 0, 5),
                    'tarifa_aplicada' => (float) $detalle->tarifa_aplicada,
                ],
            )),
        ];
    }
}

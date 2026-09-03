<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON público de un campo deportivo para ciudadanos anónimos.
 * Expone SOLO lo que un ciudadano necesita ver; nunca creado_en
 * ni campos de auditoría interna.
 */
class CampoPublicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tarifaVigente = $this->whenLoaded(
            'tarifas',
            fn () => $this->tarifas->first(),
        );

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'tipo_campo' => $this->whenLoaded('tipoCampo', fn () => [
                'id' => $this->tipoCampo->id,
                'nombre' => $this->tipoCampo->nombre,
            ]),
            'direccion' => $this->direccion,
            // DECIMAL viene como string de PostgreSQL; el mapa necesita float
            'latitud' => (float) $this->latitud,
            'longitud' => (float) $this->longitud,
            'estado' => $this->estado,
            'tarifa_vigente' => $tarifaVigente ? [
                'precio_por_hora' => (float) $tarifaVigente->precio_por_hora,
                'vigente_desde' => $tarifaVigente->vigente_desde->toIso8601String(),
            ] : null,
            'horarios_atencion' => $this->whenLoaded(
                'horariosAtencion',
                fn () => $this->horariosAtencion
                    ->sortBy('dia_semana')
                    ->map(fn ($h) => [
                        'dia_semana' => $h->dia_semana,
                        'hora_apertura' => substr($h->hora_apertura, 0, 5),
                        'hora_cierre' => substr($h->hora_cierre, 0, 5),
                    ])
                    ->values(),
            ),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampoPublicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tarifas = $this->whenLoaded('tarifas', fn () => $this->tarifas);

        $tarifaDiurna = $tarifas?->firstWhere('tipo_tarifa', 'diurna');
        $tarifaNocturna = $tarifas?->firstWhere('tipo_tarifa', 'nocturna');

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'tipo_campo' => $this->whenLoaded('tipoCampo', fn () => [
                'id' => $this->tipoCampo->id,
                'nombre' => $this->tipoCampo->nombre,
            ]),
            'direccion' => $this->direccion,
            'imagen_url' => $this->imagen_url,
            'latitud' => (float) $this->latitud,
            'longitud' => (float) $this->longitud,
            'estado' => $this->estado,
            'hora_inicio_noche' => $this->hora_inicio_noche,
            'tarifas' => [
                'diurna' => $tarifaDiurna ? [
                    'precio_por_hora' => (float) $tarifaDiurna->precio_por_hora,
                    'vigente_desde' => $tarifaDiurna->vigente_desde->toIso8601String(),
                ] : null,
                'nocturna' => $tarifaNocturna ? [
                    'precio_por_hora' => (float) $tarifaNocturna->precio_por_hora,
                    'vigente_desde' => $tarifaNocturna->vigente_desde->toIso8601String(),
                ] : null,
            ],
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

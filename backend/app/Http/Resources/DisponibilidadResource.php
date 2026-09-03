<?php

namespace App\Http\Resources;

use App\Models\CampoDeportivo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Grilla de disponibilidad horaria pública.
 * Se construye manualmente (no desde un modelo), por eso recibe
 * el campo y la grilla ya calculada por el service.
 */
class DisponibilidadResource extends JsonResource
{
    public function __construct(
        private CampoDeportivo $campo,
        private array $grilla,
    ) {
        parent::__construct($campo);
    }

    public function toArray(Request $request): array
    {
        return [
            'campo_id' => $this->campo->id,
            'nombre_campo' => $this->campo->nombre,
            'estado_campo' => $this->campo->estado,
            'fecha' => $this->grilla['fecha'],
            'abierto' => $this->grilla['abierto'],
            'bloques' => $this->grilla['bloques'],
        ];
    }
}
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tabla' => $this->tabla,
            'registro_id' => $this->registro_id,
            'accion' => $this->accion,
            'usuario_id' => $this->usuario_id,
            'usuario_nombre' => $this->usuario?->nombre_completo,
            'fecha' => $this->fecha?->toIso8601String(),
            'antes' => $this->datos_anteriores,
            'despues' => $this->datos_nuevos,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación pública-admin de una asociación con convenio.
 * Solo viaja por rutas role:admin_parametricas (CRUD) y la lectura de
 * ventanilla por-responsable/{ci}; no expone nada que no sea de gestión.
 */
class AsociacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nit' => $this->nit,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo?->value,
            'convenio_numero' => $this->convenio_numero,
            'convenio_desde' => $this->convenio_desde?->toDateString(),
            'convenio_hasta' => $this->convenio_hasta?->toDateString(),
            'responsable_nombre' => $this->responsable_nombre,
            'responsable_ci' => $this->responsable_ci,
            'responsable_telefono' => $this->responsable_telefono,
            'activo' => (bool) $this->activo,
            // Derivado pa' que el front sepa si puede ofrecer convenio YA.
            'vigente' => $this->esVigente(),
        ];
    }
}

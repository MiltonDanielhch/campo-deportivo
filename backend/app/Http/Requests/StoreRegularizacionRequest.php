<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * [B77-B] Regularización de uso tras caída (admin-only). Igual que la venta
 * pero con motivo obligatorio y sin gate de tiempo (el uso ya ocurrió).
 */
class StoreRegularizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el control de rol lo hace el controller (esAdministrador).
    }

    public function rules(): array
    {
        return [
            'franjas' => ['required', 'array', 'min:1'],
            'franjas.*.campo_id' => ['required', 'string', 'exists:campos_deportivos,id'],
            'franjas.*.fecha' => ['required', 'date'],
            'franjas.*.hora_inicio' => ['required', 'date_format:H:i:s'],
            'franjas.*.hora_fin' => ['required', 'date_format:H:i:s'],
            'franjas.*.variante_id' => ['nullable', 'string', 'exists:arancel_variantes,id'],

            'servicios' => ['nullable', 'array'],
            'servicios.*.campo_id' => ['required_with:servicios', 'string', 'exists:campos_deportivos,id'],
            'servicios.*.variante_id' => ['required_with:servicios', 'string', 'exists:arancel_variantes,id'],
            'servicios.*.cantidad' => ['nullable', 'integer', 'min:1'],

            'nombre_pagador' => ['required', 'string', 'max:150'],
            'ci_nit_pagador' => ['required', 'string', 'max:30'],
            'telefono_pagador' => ['nullable', 'string', 'max:30'],

            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Indicá el motivo de la regularización (ej. caída del sistema, SIREB sin respuesta).',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres para que la auditoría sea útil.',
        ];
    }
}

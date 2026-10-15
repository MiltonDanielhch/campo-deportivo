<?php

namespace App\Http\Requests\Piscina;

use Illuminate\Foundation\Http\FormRequest;

class StorePiscinaReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Endpoint público
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'turno_id' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.perfil' => ['required', 'in:mayor_12,menor_12,institucion'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:50'],
            'nombre_pagador' => ['required', 'string', 'max:255'],
            'telefono_pagador' => ['required', 'string', 'max:20'],
            'ci_nit_pagador' => ['required', 'string', 'max:20'],
            'ip_cobrador' => ['nullable', 'ip'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Debe seleccionar al menos un perfil',
            'items.min' => 'Debe seleccionar al menos un perfil',
            'items.*.cantidad.max' => 'La cantidad máxima por perfil es 50 personas',
        ];
    }
}

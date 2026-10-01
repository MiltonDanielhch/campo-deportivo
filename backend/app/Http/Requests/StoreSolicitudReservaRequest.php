<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitudReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_pagador' => ['required', 'string', 'max:150'],
            'telefono_pagador' => ['required', 'string', 'max:20'],
            // SIREB v1: el CI/NIT es obligatorio para crear liquidaciones
            'ci_nit_pagador' => [
                'required',
                'string',
                'max:20',
                'regex:/^[\d\-\.]+$/',   // solo dígitos, guiones y puntos
            ],
            'franjas' => ['required', 'array', 'min:1', 'max:10'],
            'franjas.*.campo_id' => ['required', 'uuid', 'exists:campos_deportivos,id'],
            'franjas.*.fecha' => ['required', 'date_format:Y-m-d'],
            'franjas.*.hora_inicio' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'franjas.*.hora_fin' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'ci_nit_pagador.required' => 'Debés ingresar tu CI o NIT para emitir la orden de cobro.',
            'ci_nit_pagador.regex' => 'El CI/NIT solo puede contener números, guiones y puntos.',
            'franjas.required' => 'Debés seleccionar al menos una franja horaria.',
            'franjas.min' => 'Debés seleccionar al menos una franja horaria.',
            'franjas.max' => 'No podés reservar más de 10 franjas en una sola solicitud.',
            'franjas.*.campo_id.exists' => 'Uno de los campos seleccionados no existe.',
            'franjas.*.fecha.date_format' => 'El formato de fecha debe ser YYYY-MM-DD.',
            'franjas.*.hora_inicio.regex' => 'El formato de hora debe ser HH:MM o HH:MM:SS.',
            'franjas.*.hora_fin.regex' => 'El formato de hora debe ser HH:MM o HH:MM:SS.',
        ];
    }
}

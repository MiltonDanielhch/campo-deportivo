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
            'nombre_pagador.required' => 'Debés ingresar el nombre o razón social del pagador.',
            'nombre_pagador.string' => 'El nombre o razón social debe ser texto.',
            'nombre_pagador.max' => 'El nombre o razón social no puede superar los 150 caracteres.',

            'telefono_pagador.required' => 'Debés ingresar un teléfono de contacto.',
            'telefono_pagador.string' => 'El teléfono de contacto debe ser texto.',
            'telefono_pagador.max' => 'El teléfono de contacto no puede superar los 20 caracteres.',

            // SIREB v1: el CI/NIT es obligatorio para crear liquidaciones.
            'ci_nit_pagador.required' => 'Debés ingresar el CI o NIT del pagador para emitir la orden de cobro.',
            'ci_nit_pagador.string' => 'El CI/NIT debe ser texto.',
            'ci_nit_pagador.max' => 'El CI/NIT no puede superar los 20 caracteres.',
            'ci_nit_pagador.regex' => 'El CI/NIT solo puede contener números, guiones y puntos.',

            'franjas.required' => 'Debés seleccionar al menos una franja horaria.',
            'franjas.min' => 'Debés seleccionar al menos una franja horaria.',
            'franjas.max' => 'No podés reservar más de 10 franjas en una sola solicitud.',

            'franjas.*.campo_id.required' => 'Cada franja debe indicar el campo deportivo.',
            'franjas.*.campo_id.uuid' => 'El identificador del campo es inválido.',
            'franjas.*.campo_id.exists' => 'Uno de los campos seleccionados no existe.',

            'franjas.*.fecha.required' => 'Cada franja debe indicar una fecha.',
            'franjas.*.fecha.date_format' => 'El formato de fecha debe ser YYYY-MM-DD.',

            'franjas.*.hora_inicio.required' => 'Cada franja debe indicar hora de inicio.',
            'franjas.*.hora_inicio.regex' => 'El formato de hora debe ser HH:MM o HH:MM:SS.',

            'franjas.*.hora_fin.required' => 'Cada franja debe indicar hora de fin.',
            'franjas.*.hora_fin.regex' => 'El formato de hora debe ser HH:MM o HH:MM:SS.',
        ];
    }

    /**
     * Nombres amigables para los mensajes de validación.
     */
    public function attributes(): array
    {
        return [
            'nombre_pagador' => 'nombre o razón social',
            'telefono_pagador' => 'teléfono de contacto',
            'ci_nit_pagador' => 'CI / NIT / identificación',
            'franjas' => 'franjas horarias',
            'franjas.*.campo_id' => 'campo deportivo',
            'franjas.*.fecha' => 'fecha de reserva',
            'franjas.*.hora_inicio' => 'hora de inicio',
            'franjas.*.hora_fin' => 'hora de fin',
        ];
    }
}

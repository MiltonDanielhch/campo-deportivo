<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de entrada de una solicitud de reserva pública.
 * Las reglas de negocio (disponibilidad, tarifa, ventana) viven
 * en SolicitudReservaService; aquí solo se valida la FORMA del payload.
 */
class StoreSolicitudReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Endpoint público: sin autenticación (Décision 4, Documento 1 v3)
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_pagador' => ['required', 'string', 'max:150'],
            'telefono_pagador' => ['required', 'string', 'max:20'],
            'ci_nit_pagador' => ['nullable', 'string', 'max:20'],
            // Tope anti-abuso: un carrito razonable no supera 10 franjas
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
            'franjas.required' => 'Debes seleccionar al menos una franja horaria.',
            'franjas.min' => 'Debes seleccionar al menos una franja horaria.',
            'franjas.max' => 'No puedes reservar más de 10 franjas en una sola solicitud.',
            'franjas.*.campo_id.exists' => 'Uno de los campos seleccionados no existe.',
            'franjas.*.fecha.date_format' => 'El formato de fecha debe ser YYYY-MM-DD.',
            'franjas.*.hora_inicio.regex' => 'El formato de hora debe ser HH:MM o HH:MM:SS.',
            'franjas.*.hora_fin.regex' => 'El formato de hora debe ser HH:MM o HH:MM:SS.',
        ];
    }
}

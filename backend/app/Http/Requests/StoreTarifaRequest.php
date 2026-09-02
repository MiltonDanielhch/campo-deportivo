<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTarifaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'precio_por_hora' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'precio_por_hora.required' => 'El precio por hora es obligatorio.',
            'precio_por_hora.numeric' => 'El precio debe ser un número.',
            'precio_por_hora.min' => 'El precio debe ser mayor a cero.',
            'precio_por_hora.max' => 'El precio no puede exceder 99999.99.',
        ];
    }
}

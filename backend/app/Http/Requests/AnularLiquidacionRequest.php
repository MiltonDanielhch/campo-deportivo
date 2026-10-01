<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularLiquidacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la hace el middleware de rol
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo de anulación es obligatorio.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
            'motivo.max' => 'El motivo no puede exceder 500 caracteres.',
        ];
    }
}

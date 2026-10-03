<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAsistenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización fina se hace en el service:
        // - rol mediante middleware;
        // - asignación de campo y ventana temporal en MarcarAsistenciaService.
        return true;
    }

    public function rules(): array
    {
        return [
            'marcar' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'marcar.required' => 'El campo marcar es obligatorio.',
            'marcar.boolean' => 'El campo marcar debe ser verdadero o falso.',
        ];
    }
}

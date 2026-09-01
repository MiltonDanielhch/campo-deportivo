<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTipoCampoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la hace el middleware de rol
    }

    public function rules(): array
    {
        $tipoCampoId = $this->route('tipoCampo')?->id;

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                $tipoCampoId
                    ? 'unique:tipos_campo,nombre,' . $tipoCampoId
                    : 'unique:tipos_campo,nombre',
            ],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del tipo de campo es obligatorio.',
            'nombre.unique' => 'Ya existe un tipo de campo con ese nombre.',
            'nombre.max' => 'El nombre no puede exceder 100 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 500 caracteres.',
        ];
    }
}

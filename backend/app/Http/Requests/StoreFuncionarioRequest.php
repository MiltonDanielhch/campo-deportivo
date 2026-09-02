<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreFuncionarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $funcionarioId = $this->route('funcionario')?->id;

        return [
            'nombre_completo' => ['required', 'string', 'max:150'],
            'ci' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9]+$/',
                $funcionarioId
                    ? Rule::unique('funcionarios', 'ci')->ignore($funcionarioId)
                    : Rule::unique('funcionarios', 'ci'),
            ],
            'usuario' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9._]+$/',
                $funcionarioId
                    ? Rule::unique('funcionarios', 'usuario')->ignore($funcionarioId)
                    : Rule::unique('funcionarios', 'usuario'),
            ],
            // La contraseña solo es obligatoria al crear; en update es opcional
            'password' => [$funcionarioId ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'rol_id' => ['required', 'uuid', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_completo.required' => 'El nombre completo es obligatorio.',
            'ci.required' => 'El CI es obligatorio.',
            'ci.unique' => 'Ya existe un funcionario con ese CI.',
            'ci.regex' => 'El CI solo permite letras mayúsculas y números.',
            'usuario.required' => 'El usuario es obligatorio.',
            'usuario.unique' => 'Ya existe un funcionario con ese usuario.',
            'usuario.regex' => 'El usuario solo permite minúsculas, números, punto y guión bajo.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'rol_id.required' => 'El rol es obligatorio.',
            'rol_id.exists' => 'El rol indicado no existe.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}

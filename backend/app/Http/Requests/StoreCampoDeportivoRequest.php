<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCampoDeportivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $campoId = $this->route('campoDeportivo')?->id;

        return [
            'tipo_campo_id' => ['required', 'uuid', 'exists:tipos_campo,id'],
            'codigo' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9\-]+$/',
                $campoId
                    ? Rule::unique('campos_deportivos', 'codigo')->ignore($campoId)
                    : Rule::unique('campos_deportivos', 'codigo'),
            ],
            'nombre' => ['required', 'string', 'max:100'],
            'direccion' => ['required', 'string', 'max:200'],
            // ⟦GATE⟧ provincia valida contra config/beni.php (borrador a validar por PO).
            // Si el array está vacío, se permite cualquier string (no bloquea dev).
            'provincia' => array_filter([
                'nullable', 'string', 'max:60',
                count((array) config('beni.provincias', [])) > 0
                    ? Rule::in(config('beni.provincias'))
                    : null,
            ]),
            'municipio' => ['nullable', 'string', 'max:80'],
            // Coordenadas dentro del departamento del Beni (aproximado)
            'latitud' => ['required', 'numeric', 'between:-18.0,-9.6'],
            'longitud' => ['required', 'numeric', 'between:-67.5,-57.4'],
            'horarios' => ['required', 'array', 'min:1', 'max:7'],
            'horarios.*.dia_semana' => ['required', 'integer', 'between:1,7'],
            'horarios.*.hora_apertura' => ['required', 'date_format:H:i'],
            'horarios.*.hora_cierre' => ['required', 'date_format:H:i', 'after:horarios.*.hora_apertura'],
            // Foto del campo (multipart): archivo opcional
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            // Flag para eliminar la foto actual sin subir otra
            'quitar_imagen' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Validación adicional: sin días duplicados.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $horarios = $this->input('horarios', []);
            $dias = array_column($horarios, 'dia_semana');

            if (count($dias) !== count(array_unique($dias))) {
                $validator->errors()->add(
                    'horarios',
                    'No puede haber dos horarios para el mismo día de la semana.'
                );
            }

            // [B97] el front restringe municipio a los de la provincia (cascada +
            // limpia al cambiar), pero el back solo validaba municipio como string.
            // Un request manipulado podía mandar municipio de otra provincia y
            // ensuciar el buscador público (B10) y el mapa. Defensa en profundidad.
            $provinciaValidada = $this->input('provincia');
            $municipioValidado = $this->input('municipio');
            if (filled($provinciaValidada) && filled($municipioValidado)) {
                $mapa = (array) config('beni.municipios_por_provincia', []);
                $permitidos = $mapa[$provinciaValidada] ?? null;
                // Si la provincia no tiene lista en config (hueco de datos, no del
                // request), no bloqueo: el front ya avisa amber pa' ese caso.
                if ($permitidos !== null && !in_array($municipioValidado, (array) $permitidos, true)) {
                    $validator->errors()->add(
                        'municipio',
                        "El municipio '{$municipioValidado}' no pertenece a la provincia '{$provinciaValidada}'.",
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código del campo es obligatorio.',
            'codigo.unique' => 'Ya existe un campo con ese código.',
            'codigo.regex' => 'El código solo permite letras mayúsculas, números y guiones.',
            'latitud.between' => 'La latitud debe estar dentro del departamento del Beni (-18.0 a -9.6).',
            'longitud.between' => 'La longitud debe estar dentro del departamento del Beni (-67.5 a -57.4).',
            'provincia.in' => 'La provincia seleccionada no es válida.',
            'municipio.string' => 'El municipio debe ser una cadena de texto.',
            'horarios.required' => 'Debe proporcionar al menos un horario de atención.',
            'horarios.*.hora_cierre.after' => 'La hora de cierre debe ser posterior a la de apertura.',
            'imagen.image' => 'El archivo debe ser una imagen (jpg, jpeg, png o webp).',
            'imagen.mimes' => 'Formatos permitidos: jpg, jpeg, png o webp.',
            'imagen.max' => 'La imagen no debe pesar más de 4 MB.',
        ];
    }

    /**
     * Forzar respuesta JSON en errores de validación.
     */
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

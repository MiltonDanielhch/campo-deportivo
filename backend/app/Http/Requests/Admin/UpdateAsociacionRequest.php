<?php

namespace App\Http\Requests\Admin;

use App\Enums\TipoAsociacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAsociacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipos = implode(',', array_column(TipoAsociacion::cases(), 'value'));
        $asociacionId = $this->route('asociacion')?->id;

        return [
            'nit' => ['sometimes', 'string', 'max:30', Rule::unique('asociaciones', 'nit')->ignore($asociacionId)],
            'nombre' => ['sometimes', 'string', 'max:150'],
            'tipo' => ['sometimes', 'nullable', 'string', 'in:' . $tipos],
            'convenio_numero' => ['sometimes', 'nullable', 'string', 'max:60'],
            'convenio_desde' => ['sometimes', 'nullable', 'date'],
            'convenio_hasta' => ['sometimes', 'nullable', 'date', 'after_or_equal:convenio_desde'],
            'responsable_nombre' => ['sometimes', 'string', 'max:150'],
            'responsable_ci' => ['sometimes', 'string', 'max:20'],
            'responsable_telefono' => ['sometimes', 'nullable', 'string', 'max:20'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Enums\TipoAsociacion;
use Illuminate\Foundation\Http\FormRequest;

class StoreAsociacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización la hace el middleware role:admin_parametricas en routes
    }

    public function rules(): array
    {
        $tipos = implode(',', array_column(TipoAsociacion::cases(), 'value'));

        return [
            'nit' => ['required', 'string', 'max:30', 'unique:asociaciones,nit'],
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['nullable', 'string', 'in:' . $tipos],
            'convenio_numero' => ['nullable', 'string', 'max:60'],
            'convenio_desde' => ['nullable', 'date'],
            'convenio_hasta' => ['nullable', 'date', 'after_or_equal:convenio_desde'],
            'responsable_nombre' => ['required', 'string', 'max:150'],
            'responsable_ci' => ['required', 'string', 'max:20'],
            'responsable_telefono' => ['nullable', 'string', 'max:20'],
            'activo' => ['boolean'],
        ];
    }
}

<?php

namespace App\Services;

use App\Models\AsignacionFuncionario;
use App\Models\CampoDeportivo;
use App\Models\Funcionario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de asignación de campos a funcionarios (HU-B3).
 *
 * Regla de negocio: solo los funcionarios con rol 'funcionario_control'
 * pueden tener campos asignados. Los admin_parametricas y gerencia
 * no reciben canchas — se rechaza la operación con mensaje claro.
 */
class AsignacionFuncionarioService
{
    private const ROL_ASIGNABLE = 'funcionario_control';

    /**
     * Asigna un campo a un funcionario.
     * Lanza ValidationException si el funcionario no tiene el rol correcto.
     */
    public function asignar(Funcionario $funcionario, CampoDeportivo $campo): AsignacionFuncionario
    {
        $this->validarRolAsignable($funcionario);

        $usuarioId = auth()->id();

        // Idempotente: si ya existe la asignación, devolverla sin crear duplicado
        $asignacion = AsignacionFuncionario::firstOrCreate(
            ['funcionario_id' => $funcionario->id, 'campo_id' => $campo->id]
        );

        AuditoriaService::registrar(
            tabla: 'asignaciones_funcionario',
            registroId: $asignacion->id,
            accion: 'asignar',
            usuarioId: $usuarioId,
            datosAnteriores: null,
            datosNuevos: [
                'funcionario_id' => $funcionario->id,
                'funcionario_usuario' => $funcionario->usuario,
                'campo_id' => $campo->id,
                'campo_codigo' => $campo->codigo,
            ],
        );

        return $asignacion;
    }

    /**
     * Desasigna un campo de un funcionario.
     */
    public function desasignar(Funcionario $funcionario, CampoDeportivo $campo): void
    {
        $usuarioId = auth()->id();

        $asignacion = AsignacionFuncionario::where('funcionario_id', $funcionario->id)
            ->where('campo_id', $campo->id)
            ->first();

        if ($asignacion) {
            $asignacionId = $asignacion->id;
            $asignacion->delete();

            AuditoriaService::registrar(
                tabla: 'asignaciones_funcionario',
                registroId: $asignacionId,
                accion: 'desasignar',
                usuarioId: $usuarioId,
                datosAnteriores: [
                    'funcionario_id' => $funcionario->id,
                    'campo_id' => $campo->id,
                ],
                datosNuevos: null,
            );
        }
    }

    /**
     * Lista los campos asignados a un funcionario específico.
     */
    public function porFuncionario(Funcionario $funcionario): Collection
    {
        return $funcionario->camposAsignados()->with('tipoCampo')->get();
    }

    /**
     * Lista todos los funcionarios con rol funcionario_control
     * que tienen al menos una asignación, junto con sus campos.
     * Útil para la pantalla de "mis asignaciones".
     */
    public function funcionariosConAsignaciones(): Collection
    {
        return Funcionario::whereHas('rol', fn ($q) => $q->where('nombre', self::ROL_ASIGNABLE))
            ->with(['camposAsignados' => fn ($q) => $q->with('tipoCampo')])
            ->where('estado', 'activo')
            ->get();
    }

    /**
     * Valida que el funcionario tenga el rol correcto para recibir asignaciones.
     * Lanza ValidationException si no cumple la regla.
     */
    private function validarRolAsignable(Funcionario $funcionario): void
    {
        $rolNombre = $funcionario->rol?->nombre ?? $funcionario->rol()->value('nombre');

        if ($rolNombre !== self::ROL_ASIGNABLE) {
            throw ValidationException::withMessages([
                'funcionario_id' => "Solo los funcionarios con rol 'funcionario_control' pueden tener campos asignados. El funcionario indicado tiene rol '$rolNombre'.",
            ]);
        }
    }
}

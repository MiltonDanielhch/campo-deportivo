<?php

namespace App\Services;

use App\Models\Rol;
use Illuminate\Validation\ValidationException;
use App\Models\Funcionario;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

/**
 * Servicio de gestión de funcionarios (HU-B1).
 *
 * Los funcionarios nunca se eliminan físicamente: se inactivan.
 * Esto preserva la trazabilidad de las tarifas que hayan creado
 * (columna tarifas_campo.creado_por) y de las auditorías.
 */
class FuncionarioService
{
    /**
     * Lista funcionarios con filtros opcionales por rol y estado.
     */
    public function listar(
        ?string $rolId = null,
        ?string $estado = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Funcionario::with('rol:id,nombre');

        if ($rolId) {
            $query->where('rol_id', $rolId);
        }

        if ($estado) {
            $query->where('estado', $estado);
        }

        return $query->orderBy('nombre_completo')->paginate($perPage);
    }

    /**
     * Crea un funcionario nuevo.
     * Registra el evento en la bitácora de auditoría.
     */
    public function crear(array $data): Funcionario
    {
        $usuarioId = auth()->id();

        $funcionario = Funcionario::create([
            'nombre_completo' => $data['nombre_completo'],
            'ci' => $data['ci'],
            'usuario' => $data['usuario'],
            'password_hash' => Hash::make($data['password']),
            'rol_id' => $data['rol_id'],
            'estado' => 'activo',
        ]);

        AuditoriaService::registrar(
            tabla: 'funcionarios',
            registroId: $funcionario->id,
            accion: 'crear',
            usuarioId: $usuarioId,
            datosAnteriores: null,
            datosNuevos: $funcionario->only(['nombre_completo', 'ci', 'usuario', 'rol_id', 'estado']),
        );

        return $funcionario->load('rol:id,nombre');
    }

    /**
     * Actualiza los datos generales de un funcionario (NO el estado).
     * Si se proporciona password, se actualiza el hash.
     */
    public function actualizar(Funcionario $funcionario, array $data): Funcionario
    {
        $usuarioId = auth()->id();
        $datosAnteriores = $funcionario->only(['nombre_completo', 'ci', 'usuario', 'rol_id']);

        $payload = [
            'nombre_completo' => $data['nombre_completo'] ?? $funcionario->nombre_completo,
            'ci' => $data['ci'] ?? $funcionario->ci,
            'usuario' => $data['usuario'] ?? $funcionario->usuario,
            'rol_id' => $data['rol_id'] ?? $funcionario->rol_id,
        ];

        // Guardrail: no degradar al último admin_parametricas activo.
        $nuevoRolId = $payload['rol_id'];
        $rolActualEsAdmin = $funcionario->rol?->nombre === 'admin_parametricas';
        $nuevoRolEsAdmin = Rol::where('id', $nuevoRolId)->value('nombre') === 'admin_parametricas';

        if ($rolActualEsAdmin && ! $nuevoRolEsAdmin) {
            $hayOtroAdminActivo = Funcionario::where('rol_id', $funcionario->rol_id)
                ->where('estado', 'activo')
                ->where('id', '!=', $funcionario->id)
                ->exists();

            if (! $hayOtroAdminActivo) {
                throw ValidationException::withMessages([
                    'rol_id' => 'No se puede quitar el rol de administrador al último admin activo.',
                ]);
            }
        }

        // Actualizar contraseña solo si se envió
        if (! empty($data['password'])) {
            $payload['password_hash'] = Hash::make($data['password']);
        }

        $funcionario->update($payload);

        AuditoriaService::registrar(
            tabla: 'funcionarios',
            registroId: $funcionario->id,
            accion: 'actualizar',
            usuarioId: $usuarioId,
            datosAnteriores: $datosAnteriores,
            datosNuevos: $funcionario->fresh()->only(['nombre_completo', 'ci', 'usuario', 'rol_id']),
        );

        return $funcionario->fresh()->load('rol:id,nombre');
    }

    /**
     * Activa o inactiva un funcionario (HU-B1).
     * No se elimina físicamente para preservar trazabilidad.
     */
    public function cambiarEstado(Funcionario $funcionario, string $nuevoEstado): Funcionario
    {
        $estadoAnterior = $funcionario->estado;
        $usuarioId = auth()->id();

        if ($nuevoEstado === 'inactivo' && $estadoAnterior === 'activo') {
            $rolNombre = Rol::where('id', $funcionario->rol_id)->value('nombre');

            if ($rolNombre === 'admin_parametricas') {
                $hayOtroAdminActivo = Funcionario::where('rol_id', $funcionario->rol_id)
                    ->where('estado', 'activo')
                    ->where('id', '!=', $funcionario->id)
                    ->exists();

                if (! $hayOtroAdminActivo) {
                    throw ValidationException::withMessages([
                        'estado' => 'No se puede inactivar al último administrador activo.',
                    ]);
                }
            }
        }

        $funcionario->update(['estado' => $nuevoEstado]);

        AuditoriaService::registrar(
            tabla: 'funcionarios',
            registroId: $funcionario->id,
            accion: 'cambiar_estado',
            usuarioId: $usuarioId,
            datosAnteriores: ['estado' => $estadoAnterior],
            datosNuevos: ['estado' => $nuevoEstado],
        );

        return $funcionario->fresh()->load('rol:id,nombre');
    }
}

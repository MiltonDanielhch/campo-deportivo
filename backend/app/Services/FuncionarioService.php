<?php

namespace App\Services;

use App\Models\Funcionario;
use App\Models\Rol;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FuncionarioService
{
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

    public function crear(array $data): Funcionario
    {
        $usuarioId = request()->attributes->get('funcionario')?->id;
        $passwordPlano = ! empty($data['password']) ? $data['password'] : Str::random(32);

        $funcionario = Funcionario::create([
            'nombre_completo' => $data['nombre_completo'],
            'ci' => $data['ci'],
            'usuario' => $data['usuario'],
            'password_hash' => Hash::make($passwordPlano),
            'mamore_id' => $data['mamore_id'] ?? null,
            'puede_login_local' => (bool) ($data['puede_login_local'] ?? false),
            'rol_id' => $data['rol_id'],
            'estado' => 'activo',
        ]);

        AuditoriaService::registrar(
            tabla: 'funcionarios',
            registroId: $funcionario->id,
            accion: 'crear',
            usuarioId: $usuarioId,
            datosAnteriores: null,
            datosNuevos: $funcionario->only([
                'nombre_completo',
                'ci',
                'usuario',
                'mamore_id',
                'puede_login_local',
                'rol_id',
                'estado',
            ]),
        );

        return $funcionario->load('rol:id,nombre');
    }

    public function actualizar(Funcionario $funcionario, array $data): Funcionario
    {
        $usuarioId = request()->attributes->get('funcionario')?->id;
        $datosAnteriores = $funcionario->only([
            'nombre_completo',
            'ci',
            'usuario',
            'mamore_id',
            'puede_login_local',
            'rol_id',
        ]);

        $payload = [
            'nombre_completo' => $data['nombre_completo'] ?? $funcionario->nombre_completo,
            'ci' => $data['ci'] ?? $funcionario->ci,
            'usuario' => $data['usuario'] ?? $funcionario->usuario,
            'rol_id' => $data['rol_id'] ?? $funcionario->rol_id,
        ];

        if (array_key_exists('mamore_id', $data)) {
            $payload['mamore_id'] = $data['mamore_id'];
        }

        if (array_key_exists('puede_login_local', $data)) {
            $payload['puede_login_local'] = (bool) $data['puede_login_local'];
        }

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
            datosNuevos: $funcionario->fresh()->only([
                'nombre_completo',
                'ci',
                'usuario',
                'mamore_id',
                'puede_login_local',
                'rol_id',
            ]),
        );

        return $funcionario->fresh()->load('rol:id,nombre');
    }

    public function cambiarEstado(Funcionario $funcionario, string $nuevoEstado): Funcionario
    {
        $estadoAnterior = $funcionario->estado;
        $usuarioId = request()->attributes->get('funcionario')?->id;

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

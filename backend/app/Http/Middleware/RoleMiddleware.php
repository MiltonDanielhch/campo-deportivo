<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Verifica que el funcionario autenticado tenga el rol requerido.
     * Uso: ->middleware('role:admin_parametricas')
     *       ->middleware('role:admin_parametricas|gerencia')  (cualquiera de los dos)
     */
    public function handle(Request $request, Closure $next, string ...$rolesPermitidos): Response
    {
        $funcionario = $request->user();

        // Sin usuario autenticado → 401 (aunque auth:sanctum ya debería haberlo rechazado antes)
        if (! $funcionario) {
            return response()->json([
                'message' => 'No autenticado',
            ], 401);
        }

        // Funcionario inactivo → 403
        if ($funcionario->estado !== 'activo') {
            return response()->json([
                'message' => 'Usuario inactivo. Contacta al administrador.',
            ], 403);
        }

        // Validar rol (el helper tienePermiso acepta wildcard '*')
        $tienePermiso = false;
        foreach ($rolesPermitidos as $rol) {
            if ($funcionario->rol?->nombre === $rol || $funcionario->tienePermiso($rol)) {
                $tienePermiso = true;
                break;
            }
        }

        if (! $tienePermiso) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta acción.',
                'roles_requeridos' => $rolesPermitidos,
                'rol_actual' => $funcionario->rol?->nombre,
            ], 403);
        }

        return $next($request);
    }
}

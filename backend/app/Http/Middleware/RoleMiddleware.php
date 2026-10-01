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
     *       ->middleware('role:admin_parametricas|gerencia')   (cualquiera de los dos)
     *       ->middleware('role:admin_parametricas,gerencia')   (coma también vale)
     *
     * Laravel separa los argumentos de middleware por COMA, por lo que un
     * string tipo "a|b" llega como UN solo argumento. Aquí normalizamos
     * tanto `|` como `,` a una lista plana de roles antes de comparar.
     */
    public function handle(Request $request, Closure $next, string ...$rolesPermitidos): Response
    {
        $funcionario = $request->user() ?? $request->attributes->get('funcionario');

        // Sin usuario autenticado → 401
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

        // Normalizar separadores: cada argumento puede traer "a|b" o "a,b".
        $rolesPermitidos = collect($rolesPermitidos)
            ->flatMap(
                fn (string $r) => preg_split('/[,|]/', $r, -1, PREG_SPLIT_NO_EMPTY)
            )
            ->map(fn (string $r) => trim($r))
            ->filter()
            ->values()
            ->all();

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

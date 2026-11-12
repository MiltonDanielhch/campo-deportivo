<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware "auth-aware" que resuelve sesión (local break-glass o JWT Ibare).
 *
 * - Sin token ni sesión → continúa anónimo (me() responde 200 + data:null).
 * - Token presente pero inválido/expirado → responde 401 con el mismo formato
 *   que auth.oauth ({"error": ...}), para no romper el contrato de API.
 * - Token válido pero funcionario no habilitado → responde 403 con el mismo
 *   formato que auth.oauth.
 *
 * No reemplaza a auth.oauth: las rutas que exigen sesión siguen bajo auth.oauth.
 */
class ResolveSesionOptional
{
    public function handle(Request $request, Closure $next): Response
    {
        // new en vez de inyección por constructor: los tests mockean
        // VerificaTokenOAuth con class@anonymous y el type hint del
        // constructor rechazaría el mock. VerificaTokenOAuth no tiene
        // dependencias, así que new es seguro y equivalente.
        $resultado = (new VerificaTokenOAuth())->resolverFuncionarioOpcional($request);

        if ($resultado instanceof Response) {
            return $resultado;
        }

        return $next($request);
    }
}

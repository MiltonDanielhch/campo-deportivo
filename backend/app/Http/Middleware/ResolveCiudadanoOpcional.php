<?php

namespace App\Http\Middleware;

use App\Models\Ciudadano;
use App\Services\AsociacionVigenteResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fase 6a: auth OPCIONAL en el canal público de reservas.
 *
 * Intenta resolver el bearer token de Sanctum. Si hay un Ciudadano logueado
 * con es_asociacion=true Y su asociación sigue vigente (re-validada al
 * reservar, no confiando en el snapshot del token), setea el atributo
 * 'convenio_asociacion' para que el controller lo pase al service.
 *
 * NUNCA aborta (no 401ea al anónimo). Si no hay token, el token es de un
 * Funcionario (no un Ciudadano), o la asociación ya no está vigente, los
 * atributos quedan null y el flujo es idéntico al anónimo de hoy.
 *
 * Lee Authorization: Bearer (token Sanctum), NO cookie de sesión. Por eso
 * same_site=lax de config/session.php no interfiere: el POST cross-origin
 * (web-public → backend) lleva el bearer en header, no en cookie.
 */
class ResolveCiudadanoOpcional
{
    public function handle(Request $request, Closure $next): Response
    {
        // Auth::guard('sanctum')->user() devuelve null sin bearer válido
        // (NO lanza AuthenticationException — el que lanza es el middleware
        // Authenticate, no el guard). Si el token es de un Funcionario,
        // instanceof Ciudadano falla → tratamos como anónimo.
        $user = Auth::guard('sanctum')->user();

        $ciudadano = $user instanceof Ciudadano ? $user : null;
        $convenioAsociacion = null;

        if ($ciudadano && $ciudadano->es_asociacion) {
            // Re-valida vigencia AL RESERVAR. El token fijó es_asociacion=true
            // al login, pero la asociación pudo inhabilitarse o vencer el
            // convenio después. Si ya no es vigente, cae a anónimo (full, 15 min).
            $convenioAsociacion = app(AsociacionVigenteResolver::class)->porCi($ciudadano->ci);
        }

        $request->attributes->set('ciudadano', $ciudadano);
        $request->attributes->set('convenio_asociacion', $convenioAsociacion);

        return $next($request);
    }
}

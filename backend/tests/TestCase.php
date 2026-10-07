<?php

namespace Tests;

use App\Http\Middleware\VerificaTokenOAuth;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * El middleware VerificaTokenOAuth valida JWT reales contra el JWKS de Ibare,
     * lo cual es imposible de simular en tests sin la clave privada del emisor.
     * Lo saltamos globalmente: la lógica de negocio (roles, reglas de estado,
     * anulación SIREB) se testea igual vía Sanctum::actingAs() + RoleMiddleware.
     *
     * Excepción: los tests que necesitan el middleware OAuth ACTIVO (p.ej.
     * AuthOAuthIbareTest) deben revertirlo en su propio setUp pasando la clase:
     *
     *     $this->withMiddleware(VerificaTokenOAuth::class);
     *
     * Ojo: withMiddleware() SIN argumentos no alcanza. Solo limpia la bandera
     * global 'middleware.disable'; lo que agrega withoutMiddleware(UnaClase) es
     * un binding en el contenedor, y para sacarlo hay que pasar la clase.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerificaTokenOAuth::class);
    }
}

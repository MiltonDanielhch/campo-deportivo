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
     * Excepción: tests que quieran probar el middleware OAuth ACTIVO (p.ej.
     * AuthOAuthIbareTest) deben re-habilitarlo en su propio setUp con
     * $this->withMiddleware() (sin argumentos restaura todos).
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerificaTokenOAuth::class);
    }
}

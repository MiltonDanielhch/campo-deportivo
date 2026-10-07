<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Superficie de autenticación de la API.
 *
 * El login local se eliminó: la autenticación humana pasa por Ibare
 * (GET /auth/login-redirect → GET /auth/callback, con authorization_code +
 * PKCE). De la API quedan GET /auth/me y POST /auth/logout.
 *
 * La validación del token (firma, expiración, issuer, JWKS cacheado y el 403
 * del funcionario sin asignación local) la cubre AuthOAuthIbareTest, que es el
 * único archivo que reactiva VerificaTokenOAuth.
 */
class AuthTest extends TestCase
{
    public function test_logout_cierra_la_sesion(): void
    {
        $this->withSession([
            'access_token' => 'token-de-prueba',
            'refresh_token' => 'refresh-de-prueba',
        ])
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesion cerrada');
    }
}

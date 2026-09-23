<?php

namespace Tests\Feature;

use App\Models\Funcionario;
use App\Models\Rol;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use phpseclib4\Crypt\RSA;   // antes: phpseclib3\Crypt\RSA

class AuthOAuthIbareTest extends TestCase
{
    use RefreshDatabase;

    private const KID = 'test-kid-canchas';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Evita que el JWKS cacheado de un test afecte al siguiente
    }

    private function clavePrivada(): string
    {
        return File::get(base_path('tests/Fixtures/oauth-test-private.pem'));
    }

    private function jwkPublico(): array
    {
        $rsa = RSA::loadPrivateKey($this->clavePrivada());
        $jwkSet = json_decode($rsa->getPublicKey()->toString('JWK'), true);

        // phpseclib devuelve un keyset {"keys":[...]}: tomamos la única clave
        $jwk = $jwkSet['keys'][0] ?? $jwkSet;

        $jwk['kid'] = self::KID;
        $jwk['use'] = 'sig';
        $jwk['alg'] = 'RS256';

        return $jwk;
    }

    private function clavePrivadaAjena(): string
    {
        return RSA::createKey(2048)->toString('PKCS8');
    }

    private function fakeJwks(): void
    {
        Http::fake([
            config('services.ibare.jwks_url') => Http::response(['keys' => [$this->jwkPublico()]]),
        ]);
    }

    private function token(array $overrides = []): string
    {
        return JWT::encode(array_merge([
            'iss' => config('services.ibare.issuer'),
            'sub' => '999999',
            'iat' => time(),
            'exp' => time() + 600,
            'scope' => 'openid',
        ], $overrides), $this->clavePrivada(), 'RS256', self::KID);
    }

    private function funcionarioHabilitado(): Funcionario
    {
        $rol = Rol::firstOrCreate(['nombre' => 'admin']);

        return Funcionario::create([
            'usuario' => 'admin',
            'nombre_completo' => 'Admin Prueba',
            'ci' => '1234567',
            'password_hash' => bcrypt('x'),
            'rol_id' => $rol->id,
            'estado' => 'activo',
            'mamore_id' => 999999,
        ]);
    }

    public function test_token_valido_de_ibare_autentica_al_funcionario_local(): void
    {
        $this->fakeJwks();
        $funcionario = $this->funcionarioHabilitado();

        $response = $this->withToken($this->token())->getJson('/api/v1/oauth/me');

        $response->assertOk()->assertJsonPath('data.id', $funcionario->id);
    }

    public function test_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/oauth/me')->assertStatus(401);
    }

    public function test_token_con_firma_de_otra_clave_devuelve_401(): void
    {
        $this->fakeJwks();

        $token = JWT::encode(
            ['iss' => config('services.ibare.issuer'), 'sub' => '999999', 'exp' => time() + 600],
            $this->clavePrivadaAjena(),
            'RS256',
            self::KID
        );

        $this->withToken($token)->getJson('/api/v1/oauth/me')->assertStatus(401);
    }

    public function test_token_expirado_devuelve_401(): void
    {
        $this->fakeJwks();
        $this->funcionarioHabilitado();

        $token = $this->token(['exp' => time() - 10, 'iat' => time() - 20]);

        $this->withToken($token)->getJson('/api/v1/oauth/me')->assertStatus(401);
    }

    public function test_issuer_distinto_devuelve_401(): void
    {
        $this->fakeJwks();
        $this->funcionarioHabilitado();

        $token = $this->token(['iss' => 'http://evil.example.com']);

        $this->withToken($token)->getJson('/api/v1/oauth/me')->assertStatus(401);
    }

    public function test_funcionario_sin_asignacion_local_devuelve_403(): void
    {
        $this->fakeJwks();
        // Nadie con mamore_id 999999 en canchas

        $this->withToken($this->token())->getJson('/api/v1/oauth/me')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FUNCIONARIO_NO_HABILITADO');
    }

    public function test_jwks_se_cachea_y_no_se_consulta_dos_veces(): void
    {
        $this->fakeJwks();
        $this->funcionarioHabilitado();

        $this->withToken($this->token())->getJson('/api/v1/oauth/me')->assertOk();
        $this->withToken($this->token())->getJson('/api/v1/oauth/me')->assertOk();

        Http::assertSentCount(1);
    }
}

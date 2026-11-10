<?php

namespace Tests\Feature\Api\V1;

use App\Models\Funcionario;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\VerificaTokenOAuth;
use Illuminate\Http\Request;

class AuthLocalBreakglassTest extends TestCase
{
    use RefreshDatabase;

    private Rol $rolAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rolAdmin = Rol::create([
            'nombre' => 'admin_parametricas',
            'descripcion' => 'Admin paramétricas',
            'permisos' => ['*'],
        ]);
    }

    private function crearFuncionario(array $overrides = []): Funcionario
    {
        return Funcionario::create(array_merge([
            'nombre_completo' => 'Emergencia Test',
            'ci' => '70000000',
            'usuario' => 'emergencia',
            'password_hash' => Hash::make('Emergencia123!'),
            'rol_id' => $this->rolAdmin->id,
            'estado' => 'activo',
            'puede_login_local' => true,
        ], $overrides));
    }

    private function sessionCookieName(): string
    {
        return (string) config('session.cookie');
    }

    private function cookieHeaderFromResponse(TestResponse $response): string
    {
        $cookies = $response->baseResponse->headers->getCookies();

        return implode('; ', array_map(
            fn (\Symfony\Component\HttpFoundation\Cookie $cookie) =>
                $cookie->getName() . '=' . $cookie->getValue(),
            $cookies
        ));
    }

    public function test_login_local_falla_si_password_invalida(): void
    {
        $this->crearFuncionario();

        $this->postJson('/api/v1/auth/local-login', [
            'usuario' => 'emergencia',
            'password' => 'Incorrecta123!',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Credenciales inválidas o acceso de emergencia no disponible');

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_login_local_falla_si_no_esta_habilitado(): void
    {
        $this->crearFuncionario([
            'puede_login_local' => false,
        ]);

        $this->postJson('/api/v1/auth/local-login', [
            'usuario' => 'emergencia',
            'password' => 'Emergencia123!',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Credenciales inválidas o acceso de emergencia no disponible');
    }

    public function test_login_local_falla_si_funcionario_inactivo(): void
    {
        $this->crearFuncionario([
            'estado' => 'inactivo',
        ]);

        $this->postJson('/api/v1/auth/local-login', [
            'usuario' => 'emergencia',
            'password' => 'Emergencia123!',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Credenciales inválidas o acceso de emergencia no disponible');
    }

    public function test_middleware_acepta_sesion_local_valida(): void
    {
        $funcionario = $this->crearFuncionario();

        $request = Request::create('/api/v1/auth/me', 'GET');
        $session = app('session')->driver();

        $session->start();
        $session->put('local_auth_funcionario_id', $funcionario->id);
        $session->put('local_auth_expira_en', now()->addMinutes(15)->timestamp);

        $request->setLaravelSession($session);

        $response = (new VerificaTokenOAuth())->handle(
            $request,
            function (Request $req) {
                return response()->json([
                    'funcionario_id' => $req->attributes->get('funcionario')?->id,
                ]);
            }
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            $funcionario->id,
            $response->getData(true)['funcionario_id']
        );
    }

    public function test_middleware_rechaza_sesion_local_expirada(): void
    {
        $funcionario = $this->crearFuncionario();

        $request = Request::create('/api/v1/auth/me', 'GET');
        $session = app('session')->driver();

        $session->start();
        $session->put('local_auth_funcionario_id', $funcionario->id);
        $session->put('local_auth_expira_en', now()->subMinute()->timestamp);

        $request->setLaravelSession($session);

        $response = (new VerificaTokenOAuth())->handle(
            $request,
            fn (Request $req) => response()->json(['ok' => true])
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_logout_limpia_claves_locales(): void
    {
        $funcionario = $this->crearFuncionario();

        $session = app('session')->driver();
        $session->start();
        $session->put('local_auth_funcionario_id', $funcionario->id);
        $session->put('local_auth_expira_en', now()->addMinutes(15)->timestamp);

        $response = app(AuthController::class)->logout();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($session->get('local_auth_funcionario_id'));
        $this->assertNull($session->get('local_auth_expira_en'));
    }
}

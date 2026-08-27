<?php

namespace App\Services;

use App\Models\Funcionario;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Intenta iniciar sesión con usuario y contraseña.
     * Devuelve token + funcionario, o null si las credenciales
     * son inválidas o el funcionario no está activo.
     */
    public function login(string $usuario, string $password): ?array
    {
        $funcionario = Funcionario::where('usuario', $usuario)->first();

        if (! $funcionario || ! Hash::check($password, $funcionario->password_hash)) {
            return null;
        }

        // Rechaza funcionarios inactivos aunque la contraseña sea correcta.
        if ($funcionario->estado !== 'activo') {
            return null;
        }

        return [
            'token' => $funcionario->createToken('panel-web')->plainTextToken,
            'funcionario' => $funcionario->load('rol'),
        ];
    }

    /**
     * Revoca SOLO el token actual, no todas las sesiones del funcionario.
     */
    public function logout(Funcionario $funcionario): void
    {
        $funcionario->currentAccessToken()->delete();
    }
}

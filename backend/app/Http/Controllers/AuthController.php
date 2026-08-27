<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'usuario' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->authService->login(
            $validated['usuario'],
            $validated['password']
        );

        if ($result === null) {
            return response()->json([
                'message' => 'Credenciales incorrectas o usuario inactivo.',
            ], 401);
        }

        return response()->json($result);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'funcionario' => $request->user()->load('rol'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}

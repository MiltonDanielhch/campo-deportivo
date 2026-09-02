<?php

namespace Database\Seeders;

use App\Models\Funcionario;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FuncionarioSeeder extends Seeder
{
    public function run(): void
    {
        // ADVERTENCIA CRÍTICA: este seeder es EXCLUSIVAMENTE para
        // desarrollo. En producción no siembra nada, aunque alguien
        // ejecute db:seed por error.
        if (! app()->environment('local')) {
            return;
        }

        // 1. Admin paramétricas: gestiona catálogos (tipos, campos, tarifas)
        Funcionario::updateOrCreate(
            ['usuario' => 'admin'],
            [
                'nombre_completo' => 'Administrador de Desarrollo',
                'ci' => '0000000',
                // Credenciales SOLO desarrollo: admin / secret
                'password_hash' => Hash::make('secret'),
                'rol_id' => Rol::where('nombre', 'admin_parametricas')->first()?->id,
                'estado' => 'activo',
            ]
        );

        // 2. Funcionario de control: supervisa el uso de campos y consulta reservas
        Funcionario::updateOrCreate(
            ['usuario' => 'control'],
            [
                'nombre_completo' => 'Control de Desarrollo',
                'ci' => '1111111',
                // Credenciales SOLO desarrollo: control / control123
                'password_hash' => Hash::make('control123'),
                'rol_id' => Rol::where('nombre', 'funcionario_control')->first()?->id,
                'estado' => 'activo',
            ]
        );
    }
}

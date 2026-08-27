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
    }
}

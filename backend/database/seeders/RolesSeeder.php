<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'nombre' => 'admin_parametricas',
                'descripcion' => 'Administra catálogos: tipos de campo, campos, horarios y tarifas.',
                'permisos' => ['gestionar-tipos-campo', 'gestionar-campos', 'gestionar-horarios', 'gestionar-tarifas', 'gestionar-funcionarios', 'ver-reservas'],
            ],
            [
                'nombre' => 'funcionario_control',
                'descripcion' => 'Consulta reservas y controla el uso de los campos deportivos.',
                'permisos' => ['ver-reservas', 'gestionar-campos'],
            ],
            [
                'nombre' => 'gerencia',
                'descripcion' => 'Acceso total de supervisión del sistema.',
                'permisos' => ['*'],
            ],
        ];

        foreach ($roles as $rol) {
            Rol::updateOrCreate(['nombre' => $rol['nombre']], $rol);
        }
    }
}

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
                // Solo puede ver reservas; los permisos específicos de
                // "ver campos asignados" se agregarán en Épica F.
                'permisos' => ['ver-reservas'],
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

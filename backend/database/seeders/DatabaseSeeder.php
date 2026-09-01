<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,             // 0.8 — 3 roles
            FuncionarioSeeder::class,       // 0.8 — admin de desarrollo
            ParametrosSistemaSeeder::class, // 1.6 — 2 parámetros operativos
        ]);
    }
}

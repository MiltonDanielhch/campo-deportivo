<?php

namespace Database\Seeders;

use App\Models\CampoDeportivo;
use App\Models\PiscinaTurno;
use App\Models\PiscinaTurnoPerfil;
use Illuminate\Database\Seeder;

class PiscinaExampleSeeder extends Seeder
{
    /**
     * Ejecuta el seeder para crear datos de ejemplo de piscina.
     *
     * Uso: php artisan db:seed --class=PiscinaExampleSeeder
     */
    public function run(): void
    {
        // Buscar la piscina por ID (del panel admin)
        $piscina = CampoDeportivo::find('01a13d85-e771-73aa-8941-371832096a75');

        if (!$piscina) {
            $this->command->warn('No se encontró el campo por ID. No se crearán turnos de ejemplo.');
            return;
        }

        // Configurar como piscina
        $piscina->update([
            'modalidad_reserva' => 'turno_por_perfil',
        ]);

        $this->command->info('Campo H. PISCINA OLIMPICA configurado como piscina.');
        $this->command->info('NOTA: Los turnos deben crearse manualmente desde el panel admin.');
    }
}

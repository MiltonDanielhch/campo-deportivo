<?php

namespace Database\Seeders;

use App\Models\CampoDeportivo;
use Illuminate\Database\Seeder;

/**
 * Mapea cada campo deportivo al servicio correspondiente en SIREB.
 *
 * Los UUIDs son los del entorno de test (obtenidos del catálogo).
 * Para producción, ejecutar `php artisan sireb:sincronizar-mapeo`
 * una vez cargado el catálogo real.
 */
class MapeoSirebSeeder extends Seeder
{
    private const MAPEO = [
        // codigo_campo_local => servicio_sireb_id
        'CD-001' => '01a0f3e8-33bc-7130-9614-85a23ea26caf',  // cancha vieja → Cancha Sintética N° 1
        'FS-001' => '01a0f3fc-b135-7119-9507-9546fcfcba7f',  // Cancha Techada → Cancha Sintética N° 2
        'CD-002' => '01a0f3fd-a930-7389-b094-bb9847f4774d',  // estadio gran mamore → Estadio Gran Mamoré
    ];

    public function run(): void
    {
        foreach (self::MAPEO as $codigoCampo => $servicioSirebId) {
            $campo = CampoDeportivo::where('codigo', $codigoCampo)->first();
            if ($campo) {
                $campo->update(['servicio_sireb_id' => $servicioSirebId]);
                $this->command->info("✓ {$codigoCampo} → {$servicioSirebId}");
            } else {
                $this->command->warn("⚠ Campo {$codigoCampo} no encontrado");
            }
        }
    }
}

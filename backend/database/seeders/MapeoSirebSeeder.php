<?php

namespace Database\Seeders;

use App\Models\CampoDeportivo;
use Illuminate\Database\Seeder;

/**
 * Mapea cada campo deportivo al servicio correspondiente en SIREB.
 *
 * Los UUIDs son los del entorno de test (obtenidos del catálogo real).
 * Es un respaldo offline de `php artisan sireb:mapear-campos`, que es la
 * vía recomendada porque lee el catálogo vigente desde SIREB y además
 * completa el código de servicio y da de alta los campos que falten.
 */
class MapeoSirebSeeder extends Seeder
{
    private const MAPEO = [
        // codigo_campo_local => [servicio_sireb_id, servicio_sireb_codigo]
        'CD-001' => ['01a0f3e8-33bc-7130-9614-85a23ea26caf', 'SEDEDE-CS1'],        // cancha vieja → Cancha Sintética N° 1
        'FS-001' => ['01a0f3fc-b135-7119-9507-9546fcfcba7f', 'SEDEDE-CS2'],        // Cancha Techada → Cancha Sintética N° 2
        'CD-002' => ['01a0f3fd-a930-7389-b094-bb9847f4774d', 'SEDEDE-EGM-ENTREN'], // estadio gran mamore → Estadio Gran Mamoré
        'CD-005' => ['01a0fe36-def0-7064-8bb9-7cb18b626c69', '0005'],              // piscina → H. PISCINA OLIMPICA
    ];

    public function run(): void
    {
        foreach (self::MAPEO as $codigoCampo => [$servicioSirebId, $servicioSirebCodigo]) {
            $campo = CampoDeportivo::where('codigo', $codigoCampo)->first();

            if (! $campo) {
                $this->command->warn("⚠ Campo {$codigoCampo} no encontrado");

                continue;
            }

            $campo->update([
                'servicio_sireb_id' => $servicioSirebId,
                'servicio_sireb_codigo' => $servicioSirebCodigo,
            ]);

            $this->command->info("✓ {$codigoCampo} → {$servicioSirebCodigo} ({$servicioSirebId})");
        }
    }
}

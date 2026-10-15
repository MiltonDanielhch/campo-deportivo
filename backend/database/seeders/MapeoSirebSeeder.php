<?php

namespace Database\Seeders;

use App\Models\CampoDeportivo;
use Illuminate\Database\Seeder;

/**
 * Respaldo OFFLINE de un único vínculo campo→SIREB, para desarrollo.
 *
 * El resto de los campos se vincula EN VIVO desde Paramétricas o con
 * `php artisan sireb:mapear-campos`, que lee el catálogo vigente de SIREB.
 * Este seeder NO es la vía normal: es solo un fallback para que el
 * Estadio Gran Mamoré tenga su servicio de test garantizado sin red.
 *
 * El UUID es del entorno TEST de SIREB → corre SOLO en local.
 */
class MapeoSirebSeeder extends Seeder
{
    private const MAPEO = [
        // codigo_campo_local => [servicio_sireb_id, servicio_sireb_codigo]
        'CD-002' => ['01a0f3fd-a930-7389-b094-bb9847f4774d', 'SEDEDE-EGM-ENTREN'], // Estadio Gran Mamoré
    ];

    public function run(): void
    {
        // Los UUIDs son de TEST. En producción el vínculo sale del comando /
        // la pantalla leyendo los UUIDs REALES, nunca de este array.
        if (! app()->environment('local')) {
            $this->command->warn('⚠ MapeoSirebSeeder omitido fuera de local (UUID de test).');

            return;
        }

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

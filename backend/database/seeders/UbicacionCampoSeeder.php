<?php

namespace Database\Seeders;

use App\Models\CampoDeportivo;
use Illuminate\Database\Seeder;

/**
 * F1 — backfill de ubicación para campos que quedaron NULL tras la migración
 * `add_ubicacion_a_campos_deportivos`.
 *
 * PARA QUÉ ESTÁ: la migración agrega provincia/municipio como nullable, así que
 * los campos existentes nacen sin ubicación y el público no puede filtrar ni
 * mostrar el chip. Este seeder los pinta UNA vez. NO es fuente de verdad: después
 * de correrlo, la ubicación la edita el operador desde el formulario de admin
 * (que valida contra config/beni.php). Es un puente de datos, no un catálogo.
 *
 * ⟦GATE Asume que HOY todas las sedes NULL del GAD Beni están en Trinidad
 * (Cercado), respaldado por el header público "Trinidad, Beni" y por Paitití
 * ("acercate a la oficina de la dependencia correspondiente"). Si el día de
 * mañana hay una sede fuera de Trinidad, NO correr este seeder en producción
 * sin antes ajustar la combinación o marcarla campo por campo desde admin.
 *
 * Fail-closed: valida la combinación contra config/beni.php antes de escribir.
 * Si el catálogo oficial no contiene Cercado/Trinidad (p. ej. el PO lo cambió),
 * aborta en vez de pintar una combinación inválida — el mismo criterio que la
 * validación cruzada municipio→provincia del StoreCampoDeportivoRequest.
 *
 * Idempotente: solo toca campos con provincia IS NULL; no sobrescribe
 * ubicaciones ya puestas (ni las que el operador corrigió a mano).
 */
class UbicacionCampoSeeder extends Seeder
{
    /** Combinación de backfill según el catálogo oficial del Beni. */
    private const PROVINCIA = 'Cercado';
    private const MUNICIPIO = 'Trinidad';

    public function run(): void
    {
        $municipios = (array) config(
            "beni.municipios_por_provincia." . self::PROVINCIA,
            []
        );

        if (! in_array(self::MUNICIPIO, $municipios, true)) {
            $this->command?->error(
                'Backfill abortado: la combinación '
                . self::PROVINCIA . '/' . self::MUNICIPIO
                . ' NO existe en config/beni.php. Revisá el catálogo oficial '
                . 'antes de pintar ubicación, para no dejar datos inválidos.'
            );

            return;
        }

        $actualizados = CampoDeportivo::whereNull('provincia')->update([
            'provincia' => self::PROVINCIA,
            'municipio' => self::MUNICIPIO,
        ]);

        $this->command?->info(
            "Ubicación: {$actualizados} campo(s) marcados "
            . self::PROVINCIA . ' / ' . self::MUNICIPIO
            . ' (solo los que estaban NULL).'
        );
    }
}

<?php

namespace Database\Seeders;

use App\Models\CampoDeportivo;
use Illuminate\Database\Seeder;

/**
 * F1: ubica los campos existentes en Mamoré / Trinidad.
 *
 * ⟦GATE⟧ Asume que HOY todas las sedes del GAD Beni están en Trinidad
 * (verificable por el header público "Trinidad, Beni" y las 3 direcciones
 * de las cards). Si existe una sede fuera de Trinidad, ESTE SEEDER LA PINTA
 * MAL: verificar con el PO antes de correrlo en producción. Idempotente:
 * solo toca campos con provincia NULL, no sobrescribe ubicaciones ya puestas.
 */
class UbicacionCampoSeeder extends Seeder
{
    public function run(): void
    {
        $actualizados = CampoDeportivo::whereNull('provincia')->update([
            'provincia' => 'Mamoré',
            'municipio' => 'Trinidad',
        ]);

        $this->command?->info("Ubicación: {$actualizados} campo(s) marcados Mamoré / Trinidad (solo los que estaban NULL).");
    }
}

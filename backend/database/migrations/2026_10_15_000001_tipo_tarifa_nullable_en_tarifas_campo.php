<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La migración 2026_10_15_000000 ("espejo SIREB crudo") amplió el CHECK de
     * `tipo_tarifa` para admitir NULL (clasificaciones `unica` / `no_clasificada`,
     * donde la fuente canónica pasa a ser `clasificacion`), pero se olvidó de
     * quitar el atributo NOT NULL de la columna, heredado de su creación en
     * 2026_09_25_180000.
     *
     * Resultado: `sireb:sincronizar-tarifas` fallaba con
     * "SQLSTATE[23502] ... la columna «tipo_tarifa» viola la restricción not-null"
     * en cuanto un servicio SIREB tenía una tarifa sin señal horaria fiable
     * (p. ej. SIR-SEDEDE-CS3, cuya tarifa de etiqueta vacía se clasifica
     * `no_clasificada`). El CHECK ya permitía el NULL; sólo faltaba la columna.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE tarifas_campo ALTER COLUMN tipo_tarifa DROP NOT NULL');
    }

    public function down(): void
    {
        // Backfill defensivo: `unica`/`no_clasificada` no tienen equivalente en
        // el viejo binario, se marcan como diurna antes de re-imponer NOT NULL.
        DB::table('tarifas_campo')
            ->whereNull('tipo_tarifa')
            ->update(['tipo_tarifa' => 'diurna']);

        DB::statement('ALTER TABLE tarifas_campo ALTER COLUMN tipo_tarifa SET NOT NULL');
    }
};

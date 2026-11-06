<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // [B] A lo sumo una asociacion ACTIVA por responsable_ci. Predicado
        // inmutable (activo = true) porque PG exige INMUTABLE en indices
        // parciales; la variante "activo Y vigente" usa CURRENT_DATE (STABLE)
        // y no es expresable aqui. Trade-off: renovar exige inhabilitar la
        // vieja antes de activar la nueva del mismo CI.
        DB::statement(
            'CREATE UNIQUE INDEX asociaciones_responsable_ci_activo_uniq '
            . 'ON asociaciones (responsable_ci) WHERE activo = true'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS asociaciones_responsable_ci_activo_uniq');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Índice único parcial: nunca puede haber dos tarifas activas
        // (vigente_hasta IS NULL) para el mismo campo.
        // Es parcial para no afectar las filas históricas ya cerradas.
        DB::statement("
            CREATE UNIQUE INDEX uq_tarifa_activa
              ON tarifas_campo (campo_id)
              WHERE vigente_hasta IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS uq_tarifa_activa");
    }
};

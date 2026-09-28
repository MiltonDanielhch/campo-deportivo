<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Eliminar el índice único parcial antiguo (uno por campo)
        DB::statement('DROP INDEX IF EXISTS uq_tarifa_activa');

        // 2. Agregar columna tipo_tarifa con default 'diurna' para registros existentes
        Schema::table('tarifas_campo', function (Blueprint $table) {
            $table->string('tipo_tarifa', 10)
                  ->default('diurna')
                  ->after('campo_id');
        });

        // 3. Migrar datos: todas las tarifas existentes se consideran "diurnas"
        //    (si había una sola, era la general; la tratamos como diurna)
        DB::table('tarifas_campo')->update(['tipo_tarifa' => 'diurna']);

        // 4. Quitar el default para que las nuevas tarifas exijan el valor explícito
        DB::statement("ALTER TABLE tarifas_campo ALTER COLUMN tipo_tarifa DROP DEFAULT");

        // 5. Agregar CHECK para restringir valores
        DB::statement("ALTER TABLE tarifas_campo ADD CONSTRAINT chk_tipo_tarifa CHECK (tipo_tarifa IN ('diurna', 'nocturna'))");

        // 6. Nuevo índice único parcial: una tarifa activa por (campo, tipo)
        DB::statement("
            CREATE UNIQUE INDEX uq_tarifa_activa_por_tipo
              ON tarifas_campo (campo_id, tipo_tarifa)
              WHERE vigente_hasta IS NULL
        ");

        // 7. Agregar índice compuesto para consultas frecuentes (historial por campo y tipo)
        Schema::table('tarifas_campo', function (Blueprint $table) {
            $table->index(['campo_id', 'tipo_tarifa', 'vigente_hasta'], 'idx_tarifas_campo_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('tarifas_campo', function (Blueprint $table) {
            $table->dropIndex('idx_tarifas_campo_tipo');
        });

        DB::statement('DROP INDEX IF EXISTS uq_tarifa_activa_por_tipo');
        DB::statement('ALTER TABLE tarifas_campo DROP CONSTRAINT IF EXISTS chk_tipo_tarifa');
        DB::statement('ALTER TABLE tarifas_campo DROP COLUMN tipo_tarifa');

        // Restaurar el índice único original
        DB::statement("
            CREATE UNIQUE INDEX uq_tarifa_activa
              ON tarifas_campo (campo_id)
              WHERE vigente_hasta IS NULL
        ");
    }
};

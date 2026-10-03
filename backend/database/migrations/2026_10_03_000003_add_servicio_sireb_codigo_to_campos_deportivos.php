<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campos_deportivos', function (Blueprint $table) {
            // Columna opcional para guardar el código legible de SIREB (ej: SEDEDE-CS1)
            // Beneficios:
            // - Legible en admin
            // - Facilita re-mapeo si cambian UUIDs
            // - Ayuda a debugging
            // - Permite sincronizar por código si SIREB lo expone estable
            $table->string('servicio_sireb_codigo', 50)->nullable()
                  ->after('servicio_sireb_id');
            $table->index('servicio_sireb_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('campos_deportivos', function (Blueprint $table) {
            $table->dropIndex(['servicio_sireb_codigo']);
            $table->dropColumn('servicio_sireb_codigo');
        });
    }
};

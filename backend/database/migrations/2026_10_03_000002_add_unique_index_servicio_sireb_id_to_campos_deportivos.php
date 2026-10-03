<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campos_deportivos', function (Blueprint $table) {
            // Primero eliminamos el índice existente si existe
            try {
                $table->dropIndex(['servicio_sireb_id']);
            } catch (\Exception $e) {
                // Índice no existe, continuar
            }

            // Creamos índice único parcial usando DB::statement
            // PostgreSQL y MySQL 8.0+ soportan WHERE en índices únicos
            // Evita que dos campos locales apunten al mismo servicio SIREB
            // Pero permite múltiples campos con servicio_sireb_id = null
            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql' || $driver === 'mysql') {
                DB::statement(
                    'CREATE UNIQUE INDEX uq_campos_servicio_sireb ON campos_deportivos (servicio_sireb_id) WHERE servicio_sireb_id IS NOT NULL'
                );
            } else {
                // SQLite y otros: índice único normal (multiple nulls permitidos)
                $table->unique('servicio_sireb_id', 'uq_campos_servicio_sireb');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campos_deportivos', function (Blueprint $table) {
            try {
                $table->dropUnique('uq_campos_servicio_sireb');
            } catch (\Exception $e) {
                // Índice no existe, continuar
            }
            // Restauramos índice normal
            $table->index('servicio_sireb_id');
        });
    }
};

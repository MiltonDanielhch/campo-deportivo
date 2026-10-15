<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campos_deportivos', function (Blueprint $table) {
            $table->enum('modalidad_reserva', ['franja_horaria', 'turno_por_perfil', 'sin_reserva_online'])
                ->default('franja_horaria')
                ->after('servicio_sireb_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('campos_deportivos', function (Blueprint $table) {
            $table->dropColumn('modalidad_reserva');
        });
    }
};

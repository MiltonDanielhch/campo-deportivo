<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_detalle_alumno', function (Blueprint $table) {
            $table->timestamp('asistencia_marca_en')->nullable();

            $table->foreignUuid('asistencia_marca_por')
                ->nullable()
                ->constrained('funcionarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_detalle_alumno', function (Blueprint $table) {
            $table->dropForeign(['asistencia_marca_por']);
            $table->dropColumn([
                'asistencia_marca_en',
                'asistencia_marca_por',
            ]);
        });
    }
};

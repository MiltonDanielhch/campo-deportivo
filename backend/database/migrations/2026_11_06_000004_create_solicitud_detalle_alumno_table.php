<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Puente N:M: que alumnos asisten a QUE franja (detalle). Se ESCRIBE en
        // el paso 2 (multi-select de ventanilla); aca solo se crea la tabla +
        // el modelo + la relacion pa' que el shape este listo y testeable.
        Schema::create('solicitud_detalle_alumno', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('detalle_id')->constrained('solicitud_reserva_detalle')->cascadeOnDelete();
            $table->foreignUuid('alumno_id')->constrained('asociacion_alumnos')->cascadeOnDelete();
            $table->timestamp('creado_en')->useCurrent();
            $table->unique(['detalle_id', 'alumno_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_detalle_alumno');
    }
};

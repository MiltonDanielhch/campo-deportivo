<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asociacion_alumnos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // asociacion_id NO es fillable en el modelo: el controller lo setea
            // SIEMPRE desde el token del presidente logueado (nunca del payload).
            $table->foreignUuid('asociacion_id')->constrained('asociaciones')->cascadeOnDelete();
            $table->string('ci', 20);
            $table->string('nombre_completo', 120);
            $table->date('fecha_nacimiento')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->useCurrent();
            $table->unique(['asociacion_id', 'ci']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asociacion_alumnos');
    }
};

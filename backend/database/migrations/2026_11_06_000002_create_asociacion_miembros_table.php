<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asociacion_miembros', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('asociacion_id')->constrained('asociaciones')->cascadeOnDelete();
            $table->string('ci', 20);
            $table->string('nombre_completo', 120);
            $table->string('rol', 40)->default('miembro'); // presidente|tesorero|profe|miembro
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->useCurrent();
            $table->unique(['asociacion_id', 'ci']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asociacion_miembros');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciudadanos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ci', 20)->unique();
            $table->string('nombre_completo', 120);
            $table->string('telefono', 20)->nullable();
            $table->string('password_hash');
            // NO setables por mass-assignment: el controller los prende solo
            // si el CI ya es responsable_ci de una asociacion ACTIVA+VIGENTE
            // (tabla del GAD, Fase 1). Fuera de $fillable a proposito.
            $table->boolean('es_asociacion')->default(false);
            $table->foreignUuid('asociacion_id')->nullable()
                ->constrained('asociaciones')->nullOnDelete();
            $table->timestamp('creado_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudadanos');
    }
};

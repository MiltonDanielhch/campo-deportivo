<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('tabla', 50);
            $table->string('registro_id', 100); // uuid o clave natural (parametros_sistema)
            $table->string('accion', 50);       // texto libre validado en backend
            $table->foreignUuid('usuario_id')->nullable()
                ->constrained('funcionarios')->nullOnDelete();
            $table->jsonb('datos_anteriores')->nullable();
            $table->jsonb('datos_nuevos')->nullable();
            $table->timestampTz('fecha')->useCurrent();
            $table->index(['tabla', 'registro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};

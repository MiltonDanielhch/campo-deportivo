<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_funcionario', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('funcionario_id')->constrained('funcionarios')->cascadeOnDelete();
            $table->foreignUuid('campo_id')->constrained('campos_deportivos')->cascadeOnDelete();
            $table->timestampTz('asignado_en')->useCurrent();
            $table->unique(['funcionario_id', 'campo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_funcionario');
    }
};

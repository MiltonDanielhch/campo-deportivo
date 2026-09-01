<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios_atencion', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('campo_id')->constrained('campos_deportivos')->cascadeOnDelete();
            $table->smallInteger('dia_semana'); // 0=Domingo … 6=Sábado
            $table->time('hora_apertura');
            $table->time('hora_cierre');
            $table->unique(['campo_id', 'dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios_atencion');
    }
};

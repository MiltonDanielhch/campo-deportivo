<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campos_deportivos', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tipo_campo_id')->constrained('tipos_campo')->restrictOnDelete();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 150);
            $table->text('direccion');
            $table->decimal('latitud', 10, 8);  // numeric(10,8)
            $table->decimal('longitud', 11, 8); // numeric(11,8)
            $table->enum('estado', ['activo', 'mantenimiento', 'inactivo'])->default('activo');
            $table->timestampTz('creado_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campos_deportivos');
    }
};

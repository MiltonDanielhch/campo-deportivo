<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funcionarios', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('nombre_completo', 200);
            $table->string('ci', 20)->unique();
            $table->string('usuario', 50)->unique();
            $table->string('password_hash', 255);
            $table->foreignUuid('rol_id')->constrained('roles')->restrictOnDelete();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestampTz('creado_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funcionarios');
    }
};

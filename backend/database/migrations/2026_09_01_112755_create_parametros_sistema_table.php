<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros_sistema', function (Blueprint $table) {
            $table->string('clave', 100)->primary(); // PK natural: única excepción a la regla uuid
            $table->string('valor', 255);
            $table->text('descripcion')->nullable();
            $table->foreignUuid('actualizado_por')->nullable()
                ->constrained('funcionarios')->nullOnDelete();
            $table->timestampTz('actualizado_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_sistema');
    }
};

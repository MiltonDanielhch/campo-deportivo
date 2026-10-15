<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piscina_turnos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('campo_id');
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->integer('cupo_total')->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['campo_id', 'fecha', 'hora_inicio', 'hora_fin'], 'unique_turno');
            $table->foreign('campo_id')->references('id')->on('campos_deportivos')->onDelete('cascade');
            $table->index(['campo_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piscina_turnos');
    }
};

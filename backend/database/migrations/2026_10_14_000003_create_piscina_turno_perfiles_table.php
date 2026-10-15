<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piscina_turno_perfiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('turno_id');
            $table->enum('perfil', ['mayor_12', 'menor_12', 'institucion']);
            $table->integer('cupo');
            $table->integer('reservado')->default(0);
            $table->uuid('tarifa_sireb_id')->nullable();
            $table->decimal('precio_unitario', 10, 2);
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();

            $table->unique(['turno_id', 'perfil'], 'unique_turno_perfil');
            $table->foreign('turno_id')->references('id')->on('piscina_turnos')->onDelete('cascade');
            $table->index(['turno_id', 'perfil']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piscina_turno_perfiles');
    }
};

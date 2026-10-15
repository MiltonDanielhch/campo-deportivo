<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piscina_reserva_detalles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reserva_id');
            $table->uuid('turno_id');
            $table->enum('perfil', ['mayor_12', 'menor_12', 'institucion']);
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->uuid('tarifa_sireb_id')->nullable();
            $table->string('servicio_sireb_id')->nullable();
            $table->string('etiqueta_sireb')->nullable();
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->date('fecha');
            $table->timestamps();

            $table->foreign('reserva_id')->references('id')->on('piscina_reservas')->onDelete('cascade');
            $table->foreign('turno_id')->references('id')->on('piscina_turnos')->onDelete('cascade');
            $table->index(['reserva_id', 'turno_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piscina_reserva_detalles');
    }
};

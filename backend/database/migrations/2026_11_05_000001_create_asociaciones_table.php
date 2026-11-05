<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asociaciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nit')->unique();
            $table->string('nombre');
            // depto | muni | null (asociación sin convenio activo pero registrada)
            $table->string('tipo')->nullable();
            $table->string('convenio_numero')->nullable();
            $table->date('convenio_desde')->nullable();
            $table->date('convenio_hasta')->nullable();
            // El presidente/responsable reserva con SU CI; el gate por identidad
            // (Fase 2b) y el lookup de convenio usan esta columna.
            $table->string('responsable_nombre');
            $table->string('responsable_ci')->index();
            $table->string('responsable_telefono')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asociaciones');
    }
};

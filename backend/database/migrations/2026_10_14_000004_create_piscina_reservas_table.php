<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piscina_reservas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo_seguimiento')->unique();
            $table->uuid('campo_id');
            $table->enum('estado', ['pendiente', 'confirmada', 'expirada', 'cancelada', 'rechazada'])->default('pendiente');
            $table->decimal('monto_total', 10, 2);
            $table->string('nombre_pagador');
            $table->string('telefono_pagador');
            $table->string('ci_nit_pagador');
            $table->string('ip_cobrador')->nullable();
            $table->timestamp('expira_en')->nullable();
            $table->string('referencia_recaudaciones')->nullable();
            $table->uuid('liquidacion_id')->nullable();
            $table->jsonb('datos_cobro_pendiente')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->decimal('monto_confirmado', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('campo_id')->references('id')->on('campos_deportivos')->onDelete('cascade');
            $table->index(['campo_id', 'estado']);
            $table->index(['estado', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piscina_reservas');
    }
};

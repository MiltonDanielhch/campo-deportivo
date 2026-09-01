<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_reserva', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('codigo_seguimiento', 40)->unique();
            $table->decimal('monto_total', 12, 2); // lo que Canchas le pide cobrar al Core
            $table->string('nombre_pagador', 150);
            $table->string('telefono_pagador', 20);
            $table->string('ci_nit_pagador', 20)->nullable();
            $table->string('referencia_recaudaciones', 100)->nullable()->unique();
            $table->enum('estado', [
                'pendiente', 'confirmada', 'expirada', 'cancelada', 'rechazada',
            ])->default('pendiente');
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('expira_en'); // creado_en + parámetro configurable
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_reserva');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('solicitud_reserva_id')
                ->constrained('solicitudes_reserva')->restrictOnDelete();
            $table->foreignUuid('solicitud_reserva_detalle_id')
                ->constrained('solicitud_reserva_detalle')->restrictOnDelete()
                ->unique(); // ← barrera de idempotencia (Anexo A.2)
            $table->string('codigo_reserva', 20)->unique();
            $table->foreignUuid('campo_id')
                ->constrained('campos_deportivos')->restrictOnDelete();
            $table->date('fecha_reserva');       // denormalizado
            $table->time('hora_inicio');         // denormalizado
            $table->time('hora_fin');            // denormalizado
            $table->decimal('monto_pagado', 12, 2);
            $table->timestampTz('confirmado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};

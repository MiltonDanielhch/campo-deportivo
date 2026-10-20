<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_reserva_servicio', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('solicitud_reserva_id');
            $table->uuid('campo_id');
            $table->uuid('variante_id')->nullable();

            $table->uuid('servicio_sireb_id')->nullable();
            $table->uuid('tarifa_sireb_id')->nullable();
            $table->string('etiqueta_sireb', 150)->nullable();

            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);

            $table->timestamp('creado_en')->useCurrent();

            $table->foreign('solicitud_reserva_id')
                ->references('id')
                ->on('solicitudes_reserva')
                ->cascadeOnDelete();

            $table->foreign('campo_id')
                ->references('id')
                ->on('campos_deportivos')
                ->restrictOnDelete();

            $table->foreign('variante_id')
                ->references('id')
                ->on('arancel_variantes')
                ->nullOnDelete();

            $table->index('solicitud_reserva_id');
            $table->index('campo_id');
            $table->index('variante_id');
            $table->index('servicio_sireb_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_reserva_servicio');
    }
};

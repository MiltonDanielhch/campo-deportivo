<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reserva', function (Blueprint $table) {
            // UUID de la liquidación en SIREB (para anulación)
            $table->uuid('liquidacion_id')->nullable()->after('referencia_recaudaciones');
            // motivo_rechazo discrimina por qué una solicitud quedó rechazada
            $table->string('motivo_rechazo', 100)->nullable()->after('expira_en');
            $table->index('liquidacion_id');
            $table->index('motivo_rechazo');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reserva', function (Blueprint $table) {
            $table->dropIndex(['liquidacion_id']);
            $table->dropIndex(['motivo_rechazo']);
            $table->dropColumn(['liquidacion_id', 'motivo_rechazo']);
        });
    }
};

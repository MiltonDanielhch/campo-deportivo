<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reserva', function (Blueprint $table) {
            $table->jsonb('datos_cobro_pendiente')->nullable()->after('referencia_recaudaciones');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reserva', function (Blueprint $table) {
            $table->dropColumn('datos_cobro_pendiente');
        });
    }
};

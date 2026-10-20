<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_reserva_detalle', function (Blueprint $table) {
            $table->uuid('variante_id')->nullable()->after('campo_id');
            $table->uuid('servicio_sireb_id')->nullable()->after('variante_id');
            $table->uuid('tarifa_sireb_id')->nullable()->after('servicio_sireb_id');
            $table->string('etiqueta_sireb')->nullable()->after('tarifa_sireb_id');
            $table->unsignedSmallInteger('cantidad')->default(1)->after('etiqueta_sireb');

            $table->foreign('variante_id')
                ->references('id')
                ->on('arancel_variantes')
                ->nullOnDelete();

            $table->index('variante_id');
            $table->index('servicio_sireb_id');
            $table->index('tarifa_sireb_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_reserva_detalle', function (Blueprint $table) {
            $table->dropForeign(['variante_id']);
            $table->dropIndex(['variante_id']);
            $table->dropIndex(['servicio_sireb_id']);
            $table->dropIndex(['tarifa_sireb_id']);

            $table->dropColumn([
                'variante_id',
                'servicio_sireb_id',
                'tarifa_sireb_id',
                'etiqueta_sireb',
                'cantidad',
            ]);
        });
    }
};
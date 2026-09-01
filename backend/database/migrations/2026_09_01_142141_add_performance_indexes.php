<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Job de expiración de solicitudes pendientes (parcial)
        DB::statement("
            CREATE INDEX idx_solicitudes_pendientes_expiracion
              ON solicitudes_reserva (estado, expira_en)
              WHERE estado = 'pendiente'
        ");

        // 2. Reservas por campo/fecha (control en sitio y reportes)
        Schema::table('reservas', function (Blueprint $table) {
            $table->index(['campo_id', 'fecha_reserva'], 'idx_reservas_campo_fecha');
        });

        // 3. Grilla de disponibilidad pública (parcial)
        DB::statement("
            CREATE INDEX idx_detalle_campo_fecha
              ON solicitud_reserva_detalle (campo_id, fecha_reserva)
              WHERE estado_solicitud IN ('pendiente', 'confirmada')
        ");

        // 4. Mapa interactivo
        Schema::table('campos_deportivos', function (Blueprint $table) {
            $table->index(['latitud', 'longitud'], 'idx_campos_lat_lng');
        });

        // 5. Asignaciones por funcionario (pendiente desde la Fase 1.1)
        Schema::table('asignaciones_funcionario', function (Blueprint $table) {
            $table->index(['funcionario_id'], 'idx_asignaciones_funcionario');
        });

        // 6. Clientes/asociaciones frecuentes
        Schema::table('solicitudes_reserva', function (Blueprint $table) {
            $table->index(['ci_nit_pagador', 'telefono_pagador'], 'idx_solicitudes_pagador');
        });
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_solicitudes_pendientes_expiracion");
        Schema::table('reservas', fn (Blueprint $table) => $table->dropIndex('idx_reservas_campo_fecha'));
        DB::statement("DROP INDEX IF EXISTS idx_detalle_campo_fecha");
        Schema::table('campos_deportivos', fn (Blueprint $table) => $table->dropIndex('idx_campos_lat_lng'));
        Schema::table('asignaciones_funcionario', fn (Blueprint $table) => $table->dropIndex('idx_asignaciones_funcionario'));
        Schema::table('solicitudes_reserva', fn (Blueprint $table) => $table->dropIndex('idx_solicitudes_pagador'));
    }
};

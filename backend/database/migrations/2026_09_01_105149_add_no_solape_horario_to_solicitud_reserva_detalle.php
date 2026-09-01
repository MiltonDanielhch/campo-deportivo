<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Anti-doble-reserva: dos franjas del mismo campo no pueden cruzarse
        // en el tiempo mientras estén activas (pendiente o confirmada).
        // Requiere btree_gist (habilitada desde el Módulo 0.8).
        DB::statement("
            ALTER TABLE solicitud_reserva_detalle
            ADD CONSTRAINT no_solape_horario
            EXCLUDE USING gist (
              campo_id WITH =,
              rango_horario WITH &&
            )
            WHERE (estado_solicitud IN ('pendiente','confirmada'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE solicitud_reserva_detalle
            DROP CONSTRAINT IF EXISTS no_solape_horario
        ");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Función: replica el estado de la cabecera en todas sus franjas.
        DB::statement("
            CREATE OR REPLACE FUNCTION sync_estado_solicitud() RETURNS trigger AS $$
            BEGIN
              UPDATE solicitud_reserva_detalle
                 SET estado_solicitud = NEW.estado
               WHERE solicitud_reserva_id = NEW.id;
              RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Trigger: se dispara SOLO cuando cambia la columna estado de la cabecera.
        DB::statement("
            CREATE TRIGGER trg_sync_estado_solicitud
            AFTER UPDATE OF estado ON solicitudes_reserva
            FOR EACH ROW EXECUTE FUNCTION sync_estado_solicitud();
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS trg_sync_estado_solicitud ON solicitudes_reserva;");
        DB::statement("DROP FUNCTION IF EXISTS sync_estado_solicitud();");
    }
};

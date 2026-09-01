<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_reserva_detalle', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('solicitud_reserva_id')
                ->constrained('solicitudes_reserva')->cascadeOnDelete();
            $table->foreignUuid('campo_id')
                ->constrained('campos_deportivos')->restrictOnDelete();
            $table->date('fecha_reserva');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->decimal('tarifa_aplicada', 12, 2); // precio congelado al emitir
        });

        // Columna generada: rango de tiempo con zona horaria oficial.
        // La calcula PostgreSQL, no la aplicación — imposible que se desincronice.
        DB::statement("
            ALTER TABLE solicitud_reserva_detalle
            ADD COLUMN rango_horario tstzrange
            GENERATED ALWAYS AS (
              tstzrange(
                (fecha_reserva + hora_inicio) AT TIME ZONE 'America/La_Paz',
                (fecha_reserva + hora_fin) AT TIME ZONE 'America/La_Paz'
              )
            ) STORED
        ");

        // Columna denormalizada: la restricción EXCLUDE no puede evaluar
        // el estado de OTRA tabla, por eso se replica aquí y el trigger la mantiene viva.
        DB::statement("
            ALTER TABLE solicitud_reserva_detalle
            ADD COLUMN estado_solicitud varchar(30) NOT NULL DEFAULT 'pendiente'
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_reserva_detalle');
    }
};

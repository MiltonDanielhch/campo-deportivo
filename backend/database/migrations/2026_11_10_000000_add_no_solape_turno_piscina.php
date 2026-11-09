<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Paridad con cancha (no_solape_horario en solicitud_reserva_detalle):
     * garantiza a nivel BD que dos turnos ACTIVOS del mismo campo no se solapen
     * en el tiempo. Hoy el solape solo lo corta PHP (PiscinaTurnoController::store
     * y PiscinaTurnoLoteService), garantía más débil bajo carrera concurrente.
     *
     * Diferencia clave vs cancha: piscina trabaja con date + time (sin timezone),
     * así que usamos tsrange (sin tz), NO tstzrange. Copiar el tstzrange de cancha
     * introduciría un bug de timezone en la restricción.
     *
     * Rango semicerrado '[)': [10:00,12:00) NO choca con [12:00,14:00), espejando
     * la lógica half-open de PiscinaTurnoLoteService::intervalosSolapan.
     *
     * Depende de btree_gist igual que cancha (habilitada desde Módulo 0.8); se
     * asegura idempotentemente pa' que la migración sea autocontenida.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist;');

        // 0. Pre-chequeo: si ya hay turnos activos solapados (datos legacy previos
        //    al gate PHP), la restricción fallaría con un error críptico de Postgres.
        //    Cortamos antes con un mensaje accionable que lista los pares.
        $conflictos = DB::select("
            SELECT a.id AS turno_a, b.id AS turno_b
            FROM piscina_turnos a
            JOIN piscina_turnos b
              ON a.campo_id = b.campo_id
             AND a.fecha = b.fecha
             AND a.id < b.id
             AND a.estado = 'activo'
             AND b.estado = 'activo'
             AND tsrange(a.fecha + a.hora_inicio, a.fecha + a.hora_fin, '[)')
                  && tsrange(b.fecha + b.hora_inicio, b.fecha + b.hora_fin, '[)')
            LIMIT 5
        ");

        if (! empty($conflictos)) {
            $pares = collect($conflictos)
                ->map(fn ($c) => "{$c->turno_a} ↔ {$c->turno_b}")
                ->implode('; ');
            throw new \RuntimeException(
                "Existen turnos de piscina activos solapados que bloquean la restricción BD: {$pares}. "
                . "Inactivá o corregí los turnos duplicados antes de migrar."
            );
        }

        // 1. Columna generada STORED con el rango horario del turno.
        //    date + time = timestamp (sin tz) en PostgreSQL; tsrange('[)') half-open.
        DB::statement("
            ALTER TABLE piscina_turnos
            ADD COLUMN horario tsrange
            GENERATED ALWAYS AS (
              tsrange(fecha + hora_inicio, fecha + hora_fin, '[)')
            ) STORED
        ");

        // 2. Restricción de exclusión: mismo campo + rango que se toque, solo activos.
        //    btree_gist/gist_uuid_ops resuelve `campo_id WITH =`; el opclass de rango
        //    resuelve `horario WITH &&`. No hace falta especificar opclasses.
        DB::statement("
            ALTER TABLE piscina_turnos
            ADD CONSTRAINT no_solape_turno_piscina
            EXCLUDE USING gist (
              campo_id WITH =,
              horario WITH &&
            )
            WHERE (estado = 'activo')
        ");
    }

    public function down(): void
    {
        // El orden importa: primero la restricción (depende de la columna),
        // después la columna generada.
        DB::statement("
            ALTER TABLE piscina_turnos
            DROP CONSTRAINT IF EXISTS no_solape_turno_piscina
        ");
        DB::statement("
            ALTER TABLE piscina_turnos
            DROP COLUMN IF EXISTS horario
        ");
    }
};

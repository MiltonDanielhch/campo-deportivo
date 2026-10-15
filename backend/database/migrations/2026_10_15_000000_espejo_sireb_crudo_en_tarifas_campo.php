<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Columnas crudas de SIREB (N1: hoy la tabla no guarda NADA del origen).
        Schema::table('tarifas_campo', function (Blueprint $table) {
            $table->uuid('tarifa_sireb_id')->nullable()->after('campo_id');   // llave real del upsert
            $table->uuid('servicio_sireb_id')->nullable()->after('tarifa_sireb_id');
            $table->string('etiqueta_sireb')->nullable()->after('servicio_sireb_id');
            $table->boolean('liquidable')->default(true)->after('etiqueta_sireb');
            $table->string('tarifario_estado', 20)->nullable()->after('liquidable'); // vigente|programado|derogado
            $table->string('estado_tarifa_sireb', 20)->nullable()->after('tarifario_estado'); // activo|inactivo
            $table->decimal('rango_desde', 12, 2)->nullable()->after('estado_tarifa_sireb');
            $table->decimal('rango_hasta', 12, 2)->nullable()->after('rango_desde');
            $table->jsonb('categorias_sireb')->nullable()->after('rango_hasta'); // señal oficial de horario (N12)
            $table->string('perfil', 30)->nullable()->after('categorias_sireb'); // mayor_12|menor_12|institucion (piscina)
            $table->string('clasificacion', 20)->default('no_clasificada')->after('perfil'); // diurna|nocturna|unica|no_clasificada
            $table->timestampTz('sincronizado_en')->nullable()->after('clasificacion'); // cuándo lo espejeamos (N13)
        });

        // 2. DEV: re-espejar limpio. Las filas viejas no tienen tarifa_sireb_id ni
        //    clasificacion fiable; el sync las recrea con el shape nuevo. En PRODUCCIÓN
        //    reemplazar este truncate por un backfill (mapear tipo_tarifa→clasificacion
        //    y dejar tarifa_sireb_id null hasta el primer sync real).
        DB::table('tarifas_campo')->truncate();

        // 3. Ampliar el CHECK de tipo_tarifa para admitir null (unica/no_clasificada)
        //    sin romper a quien ya lee tipo_tarifa='diurna'/'nocturna' (N11).
        DB::statement("ALTER TABLE tarifas_campo DROP CONSTRAINT IF EXISTS chk_tipo_tarifa");
        DB::statement("ALTER TABLE tarifas_campo ADD CONSTRAINT chk_tipo_tarifa CHECK (tipo_tarifa IS NULL OR tipo_tarifa IN ('diurna','nocturna'))");

        // 4. Nuevo CHECK sobre la columna canónica (reemplaza al binario como fuente).
        DB::statement("ALTER TABLE tarifas_campo ADD CONSTRAINT chk_clasificacion CHECK (clasificacion IN ('diurna','nocturna','unica','no_clasificada'))");

        // 5. Drop del unique por (campo,tipo) — causa de N7 (mal-clasificación pisa en silencio).
        DB::statement('DROP INDEX IF EXISTS uq_tarifa_activa_por_tipo');

        // 6. Unique nuevo por tarifa_sireb_id: cada tarifa de SIREB tiene su fila;
        //    piscina puede tener 7 sin violar el esquema. Parcial: solo activas.
        DB::statement("
            CREATE UNIQUE INDEX uq_tarifa_activa_por_sireb
              ON tarifas_campo (campo_id, tarifa_sireb_id)
              WHERE vigente_hasta IS NULL AND tarifa_sireb_id IS NOT NULL
        ");

        // 7. Índice de lectura del resolvedor (N9-read): espejo vigente por campo+clasificación.
        Schema::table('tarifas_campo', function (Blueprint $table) {
            $table->index(['campo_id', 'clasificacion', 'vigente_hasta'], 'idx_tarifas_espejo_resolvedor');
        });
    }

    public function down(): void
    {
        Schema::table('tarifas_campo', function (Blueprint $table) {
            $table->dropIndex('idx_tarifas_espejo_resolvedor');
            $table->dropColumn([
                'tarifa_sireb_id','servicio_sireb_id','etiqueta_sireb','liquidable',
                'tarifario_estado','estado_tarifa_sireb','rango_desde','rango_hasta',
                'categorias_sireb','perfil','clasificacion','sincronizado_en',
            ]);
        });
        DB::statement('DROP INDEX IF EXISTS uq_tarifa_activa_por_sireb');
        DB::statement('ALTER TABLE tarifas_campo DROP CONSTRAINT IF EXISTS chk_clasificacion');
        DB::statement('ALTER TABLE tarifas_campo DROP CONSTRAINT IF EXISTS chk_tipo_tarifa');
        DB::statement("ALTER TABLE tarifas_campo ADD CONSTRAINT chk_tipo_tarifa CHECK (tipo_tarifa IN ('diurna','nocturna'))");
        DB::statement("
            CREATE UNIQUE INDEX uq_tarifa_activa_por_tipo
              ON tarifas_campo (campo_id, tipo_tarifa)
              WHERE vigente_hasta IS NULL
        ");
    }
};

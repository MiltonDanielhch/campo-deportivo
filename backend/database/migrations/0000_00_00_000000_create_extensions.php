<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // pgcrypto: provee gen_random_uuid() para las PKs de todas las tablas.
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');

        // btree_gist: necesario para la restricción EXCLUDE anti-doble-reserva
        // del Módulo 1 (Fase 1.3). Habilitarlo ahora es inofensivo e idempotente.
        DB::statement('CREATE EXTENSION IF NOT EXISTS "btree_gist"');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS "btree_gist"');
        DB::statement('DROP EXTENSION IF EXISTS "pgcrypto"');
    }
};

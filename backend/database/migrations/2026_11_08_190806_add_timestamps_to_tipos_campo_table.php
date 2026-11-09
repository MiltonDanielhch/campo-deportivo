<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_campo', function (Blueprint $table) {
            $table->timestamp('created_at')->nullable()->after('estado');
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        // Opcional: si querés que los tipos existentes no aparezcan con "—",
        // podés backfilllear con ahora. Esto NO es la fecha real de creación,
        // es una fecha administrativa de normalización.
        DB::table('tipos_campo')
            ->whereNull('created_at')
            ->update([
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('tipos_campo', function (Blueprint $table) {
            $table->dropColumn(['created_at', 'updated_at']);
        });
    }
};

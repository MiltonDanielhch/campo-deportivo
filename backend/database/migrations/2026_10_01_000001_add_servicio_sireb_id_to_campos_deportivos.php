<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campos_deportivos', function (Blueprint $table) {
            $table->uuid('servicio_sireb_id')->nullable()
                  ->after('hora_inicio_noche');
            $table->index('servicio_sireb_id');
        });
    }

    public function down(): void
    {
        Schema::table('campos_deportivos', function (Blueprint $table) {
            $table->dropIndex(['servicio_sireb_id']);
            $table->dropColumn('servicio_sireb_id');
        });
    }
};

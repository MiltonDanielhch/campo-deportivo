<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campos_deportivos', function (Blueprint $table) {
            $table->time('hora_inicio_noche')
                  ->default('18:00:00')
                  ->after('imagen_url');
        });
    }

    public function down(): void
    {
        Schema::table('campos_deportivos', function (Blueprint $table) {
            $table->dropColumn('hora_inicio_noche');
        });
    }
};

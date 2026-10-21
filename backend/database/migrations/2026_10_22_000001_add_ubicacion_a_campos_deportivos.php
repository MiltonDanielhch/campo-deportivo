<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campos_deportivos', function (Blueprint $table) {
            $table->string('provincia', 60)->nullable()->after('direccion');
            $table->string('municipio', 80)->nullable()->after('provincia');
            $table->index(['provincia', 'municipio'], 'idx_campos_ubicacion');
        });
    }

    public function down(): void
    {
        Schema::table('campos_deportivos', function (Blueprint $table) {
            $table->dropIndex('idx_campos_ubicacion');
            $table->dropColumn(['provincia', 'municipio']);
        });
    }
};

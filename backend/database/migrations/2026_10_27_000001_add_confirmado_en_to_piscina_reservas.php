<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piscina_reservas', function (Blueprint $table) {
            $table->timestamp('confirmado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('piscina_reservas', function (Blueprint $table) {
            $table->dropColumn('confirmado_en');
        });
    }
};

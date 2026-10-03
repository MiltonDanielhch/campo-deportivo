<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->timestamp('asistencia_marcada_en')->nullable();

            $table->foreignUuid('asistencia_marcada_por')
                ->nullable()
                ->constrained('funcionarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropForeign(['asistencia_marcada_por']);
            $table->dropColumn([
                'asistencia_marcada_en',
                'asistencia_marcada_por',
            ]);
        });
    }
};

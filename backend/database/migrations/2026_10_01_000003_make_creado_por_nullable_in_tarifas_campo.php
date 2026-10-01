<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifas_campo', function (Blueprint $table) {
            $table->uuid('creado_por')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No hay vuelta atrás segura: si hay filas con creado_por null
        // no podemos volver a NOT NULL sin limpiarlas antes.
    }
};

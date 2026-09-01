<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas_campo', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('campo_id')->constrained('campos_deportivos')->restrictOnDelete();
            $table->decimal('precio_por_hora', 12, 2); // BOB numeric(12,2)
            $table->timestampTz('vigente_desde')->useCurrent();
            $table->timestampTz('vigente_hasta')->nullable(); // NULL = tarifa activa hoy
            $table->foreignUuid('creado_por')->constrained('funcionarios')->restrictOnDelete();
            $table->index(['campo_id', 'vigente_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas_campo');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arancel_variantes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('campo_id');
            $table->string('codigo', 50);
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();

            $table->string('tipo_uso', 50)->nullable();
            $table->uuid('servicio_sireb_id')->nullable();
            $table->string('servicio_sireb_codigo', 100)->nullable();

            $table->string('unidad_medida', 50)->default('hora');

            $table->boolean('visible_publico')->default(false);
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);

            $table->timestamps();

            $table->foreign('campo_id')
                ->references('id')
                ->on('campos_deportivos')
                ->cascadeOnDelete();

            $table->unique(['campo_id', 'codigo'], 'unique_arancel_variante_codigo_por_campo');
            $table->unique('servicio_sireb_id', 'unique_arancel_variante_servicio_sireb_id');
            $table->index(['campo_id', 'activo', 'orden']);
            $table->index('servicio_sireb_codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arancel_variantes');
    }
};
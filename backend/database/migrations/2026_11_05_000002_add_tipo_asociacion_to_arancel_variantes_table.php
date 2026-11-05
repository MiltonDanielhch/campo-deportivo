<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arancel_variantes', function (Blueprint $table) {
            // null = variante común (pública o de ventanilla sin convenio).
            // 'departamental' | 'municipal' = variante de convenio de ese tipo.
            $table->string('tipo_asociacion')->nullable()->after('visible_publico');
        });
    }

    public function down(): void
    {
        Schema::table('arancel_variantes', function (Blueprint $table) {
            $table->dropColumn('tipo_asociacion');
        });
    }
};

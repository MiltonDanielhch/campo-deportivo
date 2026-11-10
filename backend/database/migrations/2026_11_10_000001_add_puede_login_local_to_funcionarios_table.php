<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcionarios', function (Blueprint $table) {
            $table->boolean('puede_login_local')->default(false)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('funcionarios', function (Blueprint $table) {
            $table->dropColumn('puede_login_local');
        });
    }
};

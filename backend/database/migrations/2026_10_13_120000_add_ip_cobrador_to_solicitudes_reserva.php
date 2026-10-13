<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reserva', function (Blueprint $table) {
            // IP del ciudadano que creó la solicitud. Se usa para M2/M3 anti-hoarding.
            $table->string('ip_cobrador', 45)->nullable()->after('ci_nit_pagador');
            // IPv4 = hasta 15, IPv6 = hasta 45
            $table->index(['ip_cobrador', 'estado'], 'idx_ip_estado_hoarding');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reserva', function (Blueprint $table) {
            $table->dropIndex('idx_ip_estado_hoarding');
            $table->dropColumn('ip_cobrador');
        });
    }
};

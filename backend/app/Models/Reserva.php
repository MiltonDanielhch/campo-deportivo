<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reserva extends Model
{
    use HasUuids;

    protected $table = 'reservas';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'solicitud_reserva_id',
        'solicitud_reserva_detalle_id',
        'codigo_reserva',
        'campo_id',
        'fecha_reserva',
        'hora_inicio',
        'hora_fin',
        'monto_pagado',
        'confirmado_en',
        'asistencia_marcada_en',
        'asistencia_marcada_por',
    ];

    protected function casts(): array
    {
        return [
            'monto_pagado' => 'decimal:2',
            'fecha_reserva' => 'date',
            'confirmado_en' => 'datetime',
            'asistencia_marcada_en' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudReserva::class, 'solicitud_reserva_id');
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(SolicitudReservaDetalle::class, 'solicitud_reserva_detalle_id');
    }

    public function campo(): BelongsTo
    {
        return $this->belongsTo(CampoDeportivo::class, 'campo_id');
    }

    public function asistenciaMarcadaPor(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'asistencia_marcada_por');
    }
}

<?php

namespace App\Models;

use App\Enums\EstadoSolicitudReserva;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudReserva extends Model
{
    use HasUuids;

    protected $table = 'solicitudes_reserva';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'codigo_seguimiento', 'monto_total', 'monto_confirmado',
        'nombre_pagador', 'telefono_pagador', 'ci_nit_pagador',
        'referencia_recaudaciones', 'datos_cobro_pendiente',
        'liquidacion_id', 'estado', 'expira_en', 'motivo_rechazo',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoSolicitudReserva::class,
            'monto_total' => 'decimal:2',
            'monto_confirmado' => 'decimal:2',
            'datos_cobro_pendiente' => 'array',
            'creado_en' => 'datetime',
            'expira_en' => 'datetime',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(SolicitudReservaDetalle::class, 'solicitud_reserva_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'solicitud_reserva_id');
    }
}

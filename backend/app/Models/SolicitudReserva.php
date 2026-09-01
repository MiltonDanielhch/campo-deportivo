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
        'codigo_seguimiento', 'monto_total', 'nombre_pagador',
        'telefono_pagador', 'ci_nit_pagador', 'referencia_recaudaciones',
        'estado', 'expira_en',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoSolicitudReserva::class,
            'monto_total' => 'decimal:2',
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

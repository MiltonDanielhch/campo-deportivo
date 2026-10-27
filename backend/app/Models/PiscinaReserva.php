<?php

namespace App\Models;

use App\Enums\EstadoPiscinaReserva;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PiscinaReserva extends Model
{
    use HasUuids;

    protected $table = 'piscina_reservas';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'codigo_seguimiento',
        'campo_id',
        'estado',
        'monto_total',
        'nombre_pagador',
        'telefono_pagador',
        'ci_nit_pagador',
        'ip_cobrador',
        'expira_en',
        'referencia_recaudaciones',
        'liquidacion_id',
        'datos_cobro_pendiente',
        'motivo_rechazo',
        'monto_confirmado',
        'confirmado_en',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPiscinaReserva::class,
            'monto_total' => 'decimal:2',
            'monto_confirmado' => 'decimal:2',
            'confirmado_en' => 'datetime',
            'datos_cobro_pendiente' => 'array',
            'expira_en' => 'datetime',
        ];
    }

    public function campo(): BelongsTo
    {
        return $this->belongsTo(CampoDeportivo::class, 'campo_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PiscinaReservaDetalle::class, 'reserva_id');
    }
}

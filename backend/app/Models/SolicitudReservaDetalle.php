<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudReservaDetalle extends Model
{
    use HasUuids;

    protected $table = 'solicitud_reserva_detalle';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    // estado_solicitud y rango_horario NO son fillables:
    // los mantienen el trigger y la columna generada de PostgreSQL.
    protected $fillable = [
        'solicitud_reserva_id', 'campo_id', 'fecha_reserva',
        'hora_inicio', 'hora_fin', 'tarifa_aplicada',
    ];

    protected function casts(): array
    {
        return [
            'tarifa_aplicada' => 'decimal:2',
            'fecha_reserva' => 'date',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudReserva::class, 'solicitud_reserva_id');
    }

    public function campo(): BelongsTo
    {
        return $this->belongsTo(CampoDeportivo::class, 'campo_id');
    }

    public function reserva(): HasOne
    {
        return $this->hasOne(Reserva::class, 'solicitud_reserva_detalle_id');
    }
}

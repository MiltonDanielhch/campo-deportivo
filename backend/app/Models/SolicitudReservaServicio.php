<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudReservaServicio extends Model
{
    use HasUuids;

    protected $table = 'solicitud_reserva_servicio';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'solicitud_reserva_id',
        'campo_id',
        'variante_id',
        'servicio_sireb_id',
        'tarifa_sireb_id',
        'etiqueta_sireb',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'creado_en',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'creado_en' => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudReserva::class, 'solicitud_reserva_id');
    }

    public function campo(): BelongsTo
    {
        return $this->belongsTo(CampoDeportivo::class, 'campo_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ArancelVariante::class, 'variante_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiscinaReservaDetalle extends Model
{
    use HasUuids;

    protected $table = 'piscina_reserva_detalles';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'reserva_id',
        'turno_id',
        'perfil',
        'cantidad',
        'precio_unitario',
        'tarifa_sireb_id',
        'servicio_sireb_id',
        'etiqueta_sireb',
        'hora_inicio',
        'hora_fin',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'fecha' => 'date',
            'hora_inicio' => 'datetime:H:i:s',
            'hora_fin' => 'datetime:H:i:s',
        ];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(PiscinaReserva::class, 'reserva_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(PiscinaTurno::class, 'turno_id');
    }
}

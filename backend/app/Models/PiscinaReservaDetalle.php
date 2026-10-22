<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea snapshot de una reserva de piscina.
 *
 * Este detalle NO tiene estado de reserva. El estado vive en la cabecera
 * PiscinaReserva. Acá se congela lo cobrado/vendido en el momento:
 * perfil, cantidad, precio, tarifa SIREB, servicio SIREB, etiqueta y horario.
 *
 * Por eso, cuando se necesite saber si un turno tiene reservas pendientes,
 * hay que filtrar por la relación reserva(), no por una columna estado
 * inexistente en esta tabla.
 */
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
            // Recomendado: cantidad debe comportarse como número para SUM(),
            // validaciones y payloads JSON consistentes.
            'cantidad' => 'integer',

            'precio_unitario' => 'decimal:2',
            'fecha' => 'date',

            // El backend guarda hora como string/time. Este cast mantiene
            // serialización HH:MM:SS sin cambiar la migración.
            'hora_inicio' => 'datetime:H:i:s',
            'hora_fin' => 'datetime:H:i:s',
        ];
    }

    /**
     * Cabecera de reserva.
     *
     * El estado pendiente/confirmada/expirada/cancelada vive acá,
     * no en este detalle.
     */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(PiscinaReserva::class, 'reserva_id');
    }

    /**
     * Turno de piscina al que pertenece esta línea.
     */
    public function turno(): BelongsTo
    {
        return $this->belongsTo(PiscinaTurno::class, 'turno_id');
    }
}

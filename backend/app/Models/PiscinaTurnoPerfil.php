<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiscinaTurnoPerfil extends Model
{
    use HasUuids;

    protected $table = 'piscina_turno_perfiles';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'turno_id',
        'perfil',
        'cupo',
        'reservado',
        'tarifa_sireb_id',
        'precio_unitario',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
        ];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(PiscinaTurno::class, 'turno_id');
    }

    /**
     * Cupo disponible = cupo total - reservado
     */
    public function getDisponibleAttribute(): int
    {
        return max(0, $this->cupo - $this->reservado);
    }
}

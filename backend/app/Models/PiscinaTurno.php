<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PiscinaTurno extends Model
{
    use HasUuids;

    protected $table = 'piscina_turnos';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'campo_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'cupo_total',
        'estado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'hora_inicio' => 'string',
            'hora_fin' => 'string',
        ];
    }

    public function campo(): BelongsTo
    {
        return $this->belongsTo(CampoDeportivo::class, 'campo_id');
    }

    public function perfiles(): HasMany
    {
        return $this->hasMany(PiscinaTurnoPerfil::class, 'turno_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(PiscinaReservaDetalle::class, 'turno_id');
    }
}

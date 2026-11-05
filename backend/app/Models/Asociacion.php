<?php

namespace App\Models;

use App\Enums\TipoAsociacion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Asociacion extends Model
{
    use HasUuids;

    protected $table = 'asociaciones';

    protected $fillable = [
        'nit', 'nombre', 'tipo', 'convenio_numero',
        'convenio_desde', 'convenio_hasta',
        'responsable_nombre', 'responsable_ci', 'responsable_telefono', 'activo',
    ];

    protected $casts = [
        'tipo' => TipoAsociacion::class,
        'convenio_desde' => 'date',
        'convenio_hasta' => 'date',
        'activo' => 'boolean',
    ];

    /** Convenio vigente = activo + (sin fecha fin o fin >= hoy). */
    public function esVigente(): bool
    {
        if (! $this->activo) {
            return false;
        }
        if ($this->convenio_hasta === null) {
            return true;
        }
        return $this->convenio_hasta->gte(now()->startOfDay());
    }

    /** Fase 3: catalogo de alumnos del presidente (1:N). */
    public function alumnos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AsociacionAlumno::class, 'asociacion_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AsociacionAlumno extends Model
{
    use HasUuids;

    // Patron explicito del repo (Ciudadano / AsociacionMiembro / Detalle), no la
    // omision de Asociacion: $incrementing=false + keyType string, pa' uuid PK.
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $table = 'asociacion_alumnos';

    // asociacion_id DELIBERADAMENTE fuera de fillable: se setea por propiedad en
    // el controller desde el token (igual es_asociacion en Ciudadano). Mass-
    // assignment jamas puede mover un alumno a otra asociacion.
    protected $fillable = [
        'ci',
        'nombre_completo',
        'fecha_nacimiento',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'activo' => 'boolean',
            'creado_en' => 'datetime',
        ];
    }

    public function asociacion(): BelongsTo
    {
        return $this->belongsTo(Asociacion::class, 'asociacion_id');
    }

    public function franjas(): BelongsToMany
    {
        return $this->belongsToMany(
            SolicitudReservaDetalle::class,
            'solicitud_detalle_alumno',
            'alumno_id',
            'detalle_id'
        );
    }
}

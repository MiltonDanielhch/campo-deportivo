<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudDetalleAlumno extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $table = 'solicitud_detalle_alumno';

    protected $fillable = [
        'detalle_id',
        'alumno_id',
    ];

    protected function casts(): array
    {
        return [
            'creado_en' => 'datetime',
        ];
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(SolicitudReservaDetalle::class, 'detalle_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(AsociacionAlumno::class, 'alumno_id');
    }
}

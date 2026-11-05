<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsociacionMiembro extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $table = 'asociacion_miembros';

    protected $fillable = [
        'asociacion_id',
        'ci',
        'nombre_completo',
        'rol',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'creado_en' => 'datetime',
        ];
    }

    public function asociacion(): BelongsTo
    {
        return $this->belongsTo(Asociacion::class, 'asociacion_id');
    }
}

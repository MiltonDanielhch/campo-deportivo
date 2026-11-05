<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Ciudadano extends Authenticatable
{
    use HasUuids, HasApiTokens;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // convencion del repo: creado_en, no timestamps Laravel

    protected $table = 'ciudadanos';

    // es_asociacion / asociacion_id deliberadamente FUERA: se setean solo en
    // CiudadanoAuthController tras validar contra la tabla asociaciones (GAD).
    protected $fillable = [
        'ci',
        'nombre_completo',
        'telefono',
        'password_hash',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'es_asociacion' => 'boolean',
            'creado_en' => 'datetime',
        ];
    }

    // Sanctum espera 'password'; la columna se llama 'password_hash' (igual Funcionario).
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function asociacion(): BelongsTo
    {
        return $this->belongsTo(Asociacion::class, 'asociacion_id');
    }
}

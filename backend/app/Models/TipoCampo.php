<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoCampo extends Model
{
    use HasUuids;

    protected $table = 'tipos_campo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = ['nombre', 'descripcion', 'estado'];

    protected $appends = [
        'creado_en',
    ];

    public function camposDeportivos(): HasMany
    {
        return $this->hasMany(CampoDeportivo::class, 'tipo_campo_id');
    }

    public function getCreadoEnAttribute(): ?string
    {
        return $this->created_at?->toIso8601String();
    }
}

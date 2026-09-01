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
    public $timestamps = false; // la tabla no tiene created_at/updated_at

    protected $fillable = ['nombre', 'descripcion', 'estado'];

    public function camposDeportivos(): HasMany
    {
        return $this->hasMany(CampoDeportivo::class, 'tipo_campo_id');
    }
}

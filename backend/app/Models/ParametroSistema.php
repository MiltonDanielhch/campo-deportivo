<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParametroSistema extends Model
{
    // Sin HasUuids: la PK es la clave natural textual.
    protected $table = 'parametros_sistema';
    protected $primaryKey = 'clave';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['clave', 'valor', 'descripcion', 'actualizado_por'];

    protected function casts(): array
    {
        return ['actualizado_en' => 'datetime'];
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'actualizado_por');
    }
}

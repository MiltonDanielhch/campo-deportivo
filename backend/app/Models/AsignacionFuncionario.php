<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionFuncionario extends Model
{
    use HasUuids;

    protected $table = 'asignaciones_funcionario';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['funcionario_id', 'campo_id', 'asignado_en'];

    protected function casts(): array
    {
        return ['asignado_en' => 'datetime'];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'funcionario_id');
    }

    public function campo(): BelongsTo
    {
        return $this->belongsTo(CampoDeportivo::class, 'campo_id');
    }
}

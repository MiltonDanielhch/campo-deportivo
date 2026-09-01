<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorarioAtencion extends Model
{
    use HasUuids;

    protected $table = 'horarios_atencion';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['campo_id', 'dia_semana', 'hora_apertura', 'hora_cierre'];

    public function campo(): BelongsTo
    {
        return $this->belongsTo(CampoDeportivo::class, 'campo_id');
    }
}

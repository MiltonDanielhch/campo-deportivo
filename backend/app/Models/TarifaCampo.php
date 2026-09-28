<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifaCampo extends Model
{
    use HasUuids;

    protected $table = 'tarifas_campo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'campo_id', 'tipo_tarifa', 'precio_por_hora', 'vigente_desde', 'vigente_hasta', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'precio_por_hora' => 'decimal:2',
            'vigente_desde' => 'datetime',
            'vigente_hasta' => 'datetime',
        ];
    }

    public function campo(): BelongsTo
    {
        return $this->belongsTo(CampoDeportivo::class, 'campo_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'creado_por');
    }

    public function esActiva(): bool
    {
        return $this->vigente_hasta === null;
    }
}

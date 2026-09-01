<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampoDeportivo extends Model
{
    use HasUuids;

    protected $table = 'campos_deportivos';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'tipo_campo_id', 'codigo', 'nombre', 'direccion',
        'latitud', 'longitud', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'latitud' => 'decimal:8',
            'longitud' => 'decimal:8',
            'creado_en' => 'datetime',
        ];
    }

    public function tipoCampo(): BelongsTo
    {
        return $this->belongsTo(TipoCampo::class, 'tipo_campo_id');
    }

    public function tarifas(): HasMany
    {
        return $this->hasMany(TarifaCampo::class, 'campo_id');
    }

    public function horariosAtencion(): HasMany
    {
        return $this->hasMany(HorarioAtencion::class, 'campo_id');
    }

    public function asignacionesFuncionario(): HasMany
    {
        return $this->hasMany(AsignacionFuncionario::class, 'campo_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'campo_id');
    }
}

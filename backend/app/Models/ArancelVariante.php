<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArancelVariante extends Model
{
    use HasUuids;

    protected $table = 'arancel_variantes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'campo_id',
        'codigo',
        'nombre',
        'descripcion',
        'tipo_uso',
        'servicio_sireb_id',
        'servicio_sireb_codigo',
        'unidad_medida',
        'visible_publico',
        'activo',
        'orden',
    ];

    protected $casts = [
        'visible_publico' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function campo(): BelongsTo
    {
        return $this->belongsTo(CampoDeportivo::class, 'campo_id');
    }

    /**
     * Tarifas espejo associadas al servicio SIREB de esta variante.
     *
     * Ojo: TarifaCampo sigue atado a campo_id físicamente, pero la variante
     * discrimina por servicio_sireb_id.
     */
    public function tarifas(): HasMany
    {
        return $this->hasMany(
            TarifaCampo::class,
            'servicio_sireb_id',
            'servicio_sireb_id'
        );
    }

    public function detallesReserva(): HasMany
    {
        return $this->hasMany(
            SolicitudReservaDetalle::class,
            'variante_id'
        );
    }
}
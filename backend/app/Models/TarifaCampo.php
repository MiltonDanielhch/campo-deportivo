<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TarifaCampo extends Model
{
    use HasUuids;

    protected $table = 'tarifas_campo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'campo_id',
        'tarifa_sireb_id',
        'servicio_sireb_id',
        'etiqueta_sireb',
        'liquidable',
        'tarifario_estado',
        'estado_tarifa_sireb',
        'rango_desde',
        'rango_hasta',
        'categorias_sireb',
        'perfil',
        'clasificacion',
        'tipo_tarifa',
        'precio_por_hora',
        'vigente_desde',
        'vigente_hasta',
        'sincronizado_en',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'precio_por_hora' => 'decimal:2',
            'rango_desde' => 'decimal:2',
            'rango_hasta' => 'decimal:2',
            'liquidable' => 'boolean',
            'categorias_sireb' => 'array',
            'vigente_desde' => 'datetime',
            'vigente_hasta' => 'datetime',
            'sincronizado_en' => 'datetime',
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

    /**
     * LEGACY SHIM — puente de compatibilidad.
     *
     * Permite que tests/código viejo sigan creando tarifas solo con 'tipo_tarifa'
     * mientras el sistema nuevo consume 'clasificacion' + 'tarifario_estado'.
     * No adivina por monto: solo espeja tipo_tarifa -> clasificacion cuando falta.
     *
     * TODO 2c-clean: reemplazar por TarifaCampoFactory y borrar este boot().
     */
    protected static function booted(): void
    {
        static::creating(function (self $tarifa): void {
            // viejo -> nuevo
            if (blank($tarifa->clasificacion) && filled($tarifa->tipo_tarifa)) {
                $tarifa->clasificacion = $tarifa->tipo_tarifa;
            }

            // nuevo -> viejo (para tests/asserts que aún leen tipo_tarifa)
            if (
                filled($tarifa->clasificacion)
                && blank($tarifa->tipo_tarifa)
                && in_array($tarifa->clasificacion, ['diurna', 'nocturna'], true)
            ) {
                $tarifa->tipo_tarifa = $tarifa->clasificacion;
            }

            // defaults del espejo SIREB para creates manuales/tests
            if (blank($tarifa->tarifario_estado)) {
                $tarifa->tarifario_estado = 'vigente';
            }

            if (is_null($tarifa->liquidable)) {
                $tarifa->liquidable = true;
            }
        });
    }

    public function esActiva(): bool
    {
        return $this->vigente_hasta === null;
    }

    /**
     * Invariante "ofrecible en grilla horaria": liquidable + vigente en SIREB
     * + clasificada (diurna/nocturna/unica) + SIN perfil. Excluye piscina
     * (perfil no-null) y no-clasificadas. La usan resolvedor y presenter para
     * que el cobro y el display lean el MISMO filtro (H3: un solo lugar).
     */
    public function scopeParaGrillaHoraria(Builder $query): Builder
    {
        return $query
            ->whereNull('vigente_hasta')
            ->where('liquidable', true)
            ->where('tarifario_estado', 'vigente')
            ->whereIn('clasificacion', ['diurna', 'nocturna', 'unica'])
            ->whereNull('perfil');
    }
}

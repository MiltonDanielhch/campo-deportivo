<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
    use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;


class CampoDeportivo extends Model
{
    use HasUuids;

    protected $table = 'campos_deportivos';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'tipo_campo_id', 'codigo', 'nombre', 'direccion',
        'latitud', 'longitud', 'estado', 'imagen_url', 'hora_inicio_noche',
        'servicio_sireb_id', 'servicio_sireb_codigo',
    ];

    protected function casts(): array
    {
        return [
            'latitud' => 'decimal:8',
            'longitud' => 'decimal:8',
            'creado_en' => 'datetime',
            'hora_inicio_noche' => 'string', // 'HH:MM:SS' sin parsear como datetime
        ];
    }


    /**
     * La URL pública de la foto se calcula en runtime desde el path guardado.
     * Así cambiar el driver del disco (local → s3 → gcs) no requiere migrar la BD.
     */
    protected function imagenUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Storage::disk('public')->url($value) : null,
        );
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

    public function funcionariosAsignados(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Funcionario::class,
            'asignaciones_funcionario',
            'campo_id',
            'funcionario_id'
        ); // Sin withTimestamps()
    }
}

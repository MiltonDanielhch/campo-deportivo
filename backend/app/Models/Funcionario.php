<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Funcionario extends Authenticatable
{
    use HasUuids, HasApiTokens;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // la tabla usa creado_en, no timestamps de Laravel

    protected $table = 'funcionarios';

    protected $fillable = [
        'nombre_completo',
        'ci',
        'usuario',
        'password_hash',
        'rol_id',
        'estado',
    ];

    protected $hidden = [
        'password_hash', // nunca serializar el hash en respuestas JSON
    ];

    protected function casts(): array
    {
        return [
            'creado_en' => 'datetime',
        ];
    }

    /**
     * Sanctum espera por defecto una columna 'password';
     * la nuestra se llama 'password_hash'.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function tienePermiso(string $permiso): bool
    {
        $permisos = $this->rol?->permisos ?? [];

        return in_array('*', $permisos, true)
            || in_array($permiso, $permisos, true);
    }
}

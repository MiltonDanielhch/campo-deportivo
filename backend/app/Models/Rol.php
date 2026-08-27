<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // la tabla no tiene created_at/updated_at

    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'descripcion',
        'permisos',
    ];

    protected function casts(): array
    {
        return [
            'permisos' => 'array', // jsonb → array de PHP
        ];
    }

    public function funcionarios(): HasMany
    {
        return $this->hasMany(Funcionario::class, 'rol_id');
    }
}

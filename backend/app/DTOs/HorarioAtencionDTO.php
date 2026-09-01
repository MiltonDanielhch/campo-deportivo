<?php

namespace App\DTOs;

class HorarioAtencionDTO
{
    public function __construct(
        public readonly int $dia_semana,       // 1=Lunes, 7=Domingo
        public readonly string $hora_apertura, // formato HH:MM:SS
        public readonly string $hora_cierre,   // formato HH:MM:SS
    ) {}
}

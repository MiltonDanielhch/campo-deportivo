<?php

namespace App\Enums;

enum TipoTarifaSireb: string
{
    case Diurna = 'diurna';
    case Nocturna = 'nocturna';
    case Unica = 'unica';                 // tarifa única sin corte temporal (CS3)
    case NoClasificada = 'no_clasificada'; // fail-closed: no entra a grilla horaria

    public function esOfrecibleEnGrillaHoraria(): bool
    {
        return $this !== self::NoClasificada;
    }
}

<?php

namespace App\Services\Sireb;

use App\Enums\TipoTarifaSireb;

/**
 * Resultado de clasificar UNA tarifa de SIREB. Inmutable.
 *
 * `senal` dice DE DÓNDE salió la clasificación (auditoría + debug):
 *   'categoria' → usamos categorias[grupo=horario] (la señal oficial de SIREB)
 *   'etiqueta'  → normalizamos la etiqueta de texto
 *   'unica'     → el servicio tiene una sola tarifa liquidable y sin corte
 *   'none'      → no pudimos clasificar → NoClasificada (fail-closed)
 *
 * `perfil` es orthogonal al horario: 'mayor_12' | 'menor_12' | 'institucion' | null.
 * Piscina llena perfil pero NO horario fiable → NoClasificada + perfil, para que el
 * sub-módulo de aforo la lea sin contaminar la grilla de canchas.
 */
final class ClasificacionTarifa
{
    public function __construct(
        public readonly TipoTarifaSireb $tipo,
        public readonly ?string $perfil = null,
        public readonly string $senal = 'none',
    ) {}

    public function confiableParaGrilla(): bool
    {
        return $this->tipo->esOfrecibleEnGrillaHoraria();
    }
}

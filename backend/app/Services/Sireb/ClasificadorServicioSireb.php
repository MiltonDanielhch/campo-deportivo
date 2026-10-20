<?php

namespace App\Services\Sireb;

final class ClasificadorServicioSireb
{
    public const ALQUILER_BASE = 'alquiler_base';
    public const SERVICIO_ADICIONAL = 'servicio_adicional';
    public const ARRIENDO_PERIODICO = 'arriendo_periodico';
    public const HOSPEDAJE = 'hospedaje';
    public const PISCINA = 'piscina';
    public const SERVICIO_ESPECIAL = 'servicio_especial';
    public const EVENTO_ESPECIAL = 'evento_especial';
    public const NO_CLASIFICADO = 'no_clasificado';

    /**
     * Clasifica un servicio SIREB por su naturaleza comercial.
     *
     * No confunde esto con ClasificadorTarifaSireb:
     * - este clasifica el SERVICIO: base, limpieza, energía, kiosco, hospedaje, etc.
     * - ClasificadorTarifaSireb clasifica la TARIFA: diurna, nocturna, única, perfil.
     *
     * @param  array<string, mixed>  $servicio
     * @return array{categoria: string, motivo: string}
     */
    public function clasificar(array $servicio): array
    {
        $codigo = $this->normalizar($servicio['codigo'] ?? null);
        $nombre = $this->normalizar($servicio['nombre'] ?? null);
        $descripcion = $this->normalizar($servicio['descripcion'] ?? null);
        $unidad = $this->normalizar($servicio['unidad_medida'] ?? null);

        $texto = trim($codigo.' '.$nombre.' '.$descripcion);

        // 1. Servicios adicionales claros: limpieza y energía.
        if ($this->contieneCualquiera($texto, ['limpieza'])) {
            return $this->resultado(self::SERVICIO_ADICIONAL, 'etiqueta_contiene_limpieza');
        }

        if ($this->contieneCualquiera($texto, ['energia', 'energi'])) {
            return $this->resultado(self::SERVICIO_ADICIONAL, 'etiqueta_contiene_energia');
        }

        // 2. Arriendos periódicos / mensualidades.
        // Kioscos, depósitos, cocinas, salas, cuidantes de motos.
        if (
            $unidad === 'mes'
            || $this->contieneCualquiera($texto, ['kiosco', 'deposito', 'sala para funcionamiento', 'cuidantes de motos'])
        ) {
            return $this->resultado(self::ARRIENDO_PERIODICO, 'unidad_mes_o_catalogo_periodico');
        }

        // 3. Hospedaje / complejo habitacional.
        if (
            str_starts_with($codigo, 'sedede-hab-')
            || in_array($unidad, ['persona_noche', 'dia'], true)
            || $this->contieneCualquiera($texto, ['habitacional', 'colchon'])
        ) {
            return $this->resultado(self::HOSPEDAJE, 'complejo_habitacional_o_unidad_hospedaje');
        }

        // 4. Piscina olímpica.
        if (
            $codigo === '0005'
            || $this->contieneCualquiera($texto, ['piscina'])
        ) {
            return $this->resultado(self::PISCINA, 'servicio_piscina');
        }

        // 5. Vivanderos: no son limpieza/energía, pero tampoco alquiler base simple.
        if ($this->contieneCualquiera($texto, ['vivandero', '-viv-'])) {
            return $this->resultado(self::SERVICIO_ESPECIAL, 'vivandero');
        }

        // 6. Espectáculos / pista atlética / casos de alto valor o reglas especiales.
        if ($this->contieneCualquiera($texto, ['espectaculo', 'espect', 'pista atletica', 'sin boletos'])) {
            return $this->resultado(self::EVENTO_ESPECIAL, 'espectaculo_o_pista');
        }

        // 7. Alquiler base por hora/jornada/actividad.
        if (in_array($unidad, ['hora', 'jornada', 'actividad'], true)) {
            return $this->resultado(self::ALQUILER_BASE, 'unidad_horaria_o_jornada');
        }

        // 8. Si trae código SEDEDE pero no calza, dejar visible como no clasificado.
        if ($codigo !== '') {
            return $this->resultado(self::NO_CLASIFICADO, 'sin_regla_fiable');
        }

        return $this->resultado(self::NO_CLASIFICADO, 'servicio_sin_codigo');
    }

    /**
     * @return array{categoria: string, motivo: string}
     */
    private function resultado(string $categoria, string $motivo): array
    {
        return [
            'categoria' => $categoria,
            'motivo' => $motivo,
        ];
    }

    private function contieneCualquiera(string $texto, array $patrones): bool
    {
        foreach ($patrones as $patron) {
            if (str_contains($texto, $patron)) {
                return true;
            }
        }

        return false;
    }

    private function normalizar(?string $valor): string
    {
        $valor = mb_strtolower(trim((string) $valor));

        $valor = strtr($valor, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);

        return (string) preg_replace('/\s+/', ' ', $valor);
    }
}
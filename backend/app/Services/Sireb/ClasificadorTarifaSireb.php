<?php

namespace App\Services\Sireb;

use App\Enums\TipoTarifaSireb;

/**
 * ÚNICA fuente de verdad de clasificación etiqueta→tipo en todo el sistema.
 *
 * La consumen: el sync (SincronizarTarifasSireb), el resolvedor
 * (CatalogoSirebService::resolverTarifaId), el presenter público y el panel.
 * Se acabó el "contains en un lado, strcasecmp exact en el otro" (H2/H3).
 *
 * Regla inquebrantable: NUNCA adivinar por monto. Si no hay señal fiable,
 * NoClasificada (fail-closed), igual que esSeleccionable en mobile.
 */
class ClasificadorTarifaSireb
{
    /** Patrones de etiqueta → tipo temporal. Orden: nocturno antes que diurno. */
    private const NOCTURNO = ['nocturn', 'iluminacion', 'con iluminacion', 'noche'];
    private const DIURNO   = ['diurn', 'regular', 'sin iluminacion', 'diurna'];

    /** Patrones de perfil (piscina/instituciones), orthogonales al horario. */
    private const PERFILES = [
        'mayor_12'   => ['mayores de 12', 'mayor de 12', '>12', '>=12'],
        'menor_12'   => ['menores de 12', 'menor de 12', '<12', '<=12'],
        'institucion'=> ['institucion', 'instituciones'],
    ];

    /**
     * @param array $tarifa  una tarifa del shape real de SIREB (etiqueta, categorias, ...)
     * @param array $servicio el servicio padre (para saber si es tarifa única)
     */
    public function clasificar(array $tarifa, array $servicio): ClasificacionTarifa
    {
        $perfil = $this->extraerPerfil($tarifa['etiqueta'] ?? null);

        // 1) Señal oficial de SIREB: categorias[grupo=horario].codigo
        $porCategoria = $this->desdeCategorias($tarifa['categorias'] ?? []);
        if ($porCategoria !== null) {
            return new ClasificacionTarifa($porCategoria, $perfil, 'categoria');
        }

        $etiqueta = $this->normalizar($tarifa['etiqueta'] ?? null);

        // 2) Etiqueta vacía → tarifa única si el servicio solo tiene una liquidable (CS3)
        if ($etiqueta === '') {
            $liquidables = $this->contarLiquidables($servicio);
            $tipo = $liquidables === 1 ? TipoTarifaSireb::Unica : TipoTarifaSireb::NoClasificada;
            return new ClasificacionTarifa($tipo, $perfil, $tipo === TipoTarifaSireb::Unica ? 'unica' : 'none');
        }

        // 3) Normalización de etiqueta contra sets cerrados
        if ($this->contieneCualquiera($etiqueta, self::NOCTURNO)) {
            return new ClasificacionTarifa(TipoTarifaSireb::Nocturna, $perfil, 'etiqueta');
        }
        if ($this->contieneCualquiera($etiqueta, self::DIURNO)) {
            return new ClasificacionTarifa(TipoTarifaSireb::Diurna, $perfil, 'etiqueta');
        }

        // 4) Solo perfil, sin señal temporal fiable (piscina: "Bañista mayores de 12")
        //    → NoClasificada CON perfil guardado: no entra a grilla horaria, pero el
        //    sub-módulo de aforo la recupera por perfil. Fail-closed para canchas.
        if ($perfil !== null) {
            return new ClasificacionTarifa(TipoTarifaSireb::NoClasificada, $perfil, 'none');
        }

        // 5) Nada calza → fail-closed
        return new ClasificacionTarifa(TipoTarifaSireb::NoClasificada, null, 'none');
    }

    private function desdeCategorias(array $categorias): ?TipoTarifaSireb
    {
        foreach ($categorias as $c) {
            if (($c['grupo'] ?? null) === 'horario') {
                $codigo = mb_strtolower(trim((string) ($c['codigo'] ?? '')));
                if (str_contains($codigo, 'nocturn')) return TipoTarifaSireb::Nocturna;
                if (str_contains($codigo, 'diurn'))   return TipoTarifaSireb::Diurna;
            }
        }
        return null;
    }

    private function extraerPerfil(?string $etiqueta): ?string
    {
        if ($etiqueta === null) return null;
        $e = $this->normalizar($etiqueta);
        foreach (self::PERFILES as $perfil => $patrones) {
            if ($this->contieneCualquiera($e, $patrones)) return $perfil;
        }
        return null;
    }

    private function contarLiquidables(array $servicio): int
    {
        return collect($servicio['tarifas'] ?? [])
            ->filter(fn ($t) => ($t['liquidable'] ?? false) === true)
            ->count();
    }

    private function normalizar(?string $s): string
    {
        $s = mb_strtolower(trim((string) $s));
        // sin tildes, colapsa espacios
        $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return (string) preg_replace('/\s+/', ' ', $s);
    }

    private function contieneCualquiera(string $etiqueta, array $patrones): bool
    {
        foreach ($patrones as $p) {
            if (str_contains($etiqueta, $p)) return true;
        }
        return false;
    }
}

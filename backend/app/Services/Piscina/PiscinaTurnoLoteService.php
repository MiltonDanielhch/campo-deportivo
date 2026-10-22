<?php

namespace App\Services\Piscina;

use App\Models\PiscinaTurno;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Núcleo del lote de turnos de piscina, sin HTTP/auth (testeable por tinker).
 *
 * ⟦Gate#1 cerrado con evidencia⟧ cancha prohíbe solape con EXCLUDE USING gist
 * (migración add_no_solape_horario). Piscina NO tiene restricción de rango en BD
 * (solo unique de tupla exacta campo+fecha+inicio+fin), así que el solape se
 * valida acá en PHP. Garantía más débil que cancha (carrera posible); la
 * migración timerange+EXCLUDE de piscina queda como follow-up de higiene.
 *
 * ⟦Gate#2⟧ el seeder no dio plantilla ⇒ clon-por-día-referencia: replica los
 * turnos ACTIVOS de una fecha origen (datos reales ya creados) a un rango,
 * idempotente vía unique_turno, saltando fechas con solape no-idéntico.
 */
class PiscinaTurnoLoteService
{
    /**
     * Clona los turnos activos de $fechaOrigen a cada fecha de [$desde,$hasta].
     *
     * @return array{creados:int, omitidas:string[], procesadas:string[]}
     */
    public function clonarDesdeReferencia(string $campoId, string $fechaOrigen, string $desde, string $hasta): array
    {
        $origen = PiscinaTurno::with('perfiles')
            ->where('campo_id', $campoId)
            ->where('fecha', $fechaOrigen)
            ->where('estado', 'activo')
            ->get();

        if ($origen->isEmpty()) {
            throw new InvalidArgumentException(
                "El día origen ({$fechaOrigen}) no tiene turnos activos para clonar."
            );
        }

        // El día de referencia no puede ser internamente inconsistente.
        $this->assertSinSolapeInterno($origen);

        $creados = 0;
        $omitidas = [];
        $procesadas = [];

        DB::transaction(function () use ($campoId, $origen, $desde, $hasta, &$creados, &$omitidas, &$procesadas) {
            $d = Carbon::parse($desde)->startOfDay();
            $h = Carbon::parse($hasta)->startOfDay();

            while ($d->lte($h)) {
                $fecha = $d->toDateString();
                $procesadas[] = $fecha;

                $existentes = PiscinaTurno::where('campo_id', $campoId)
                    ->where('fecha', $fecha)
                    ->where('estado', 'activo')
                    ->get();

                if ($this->hayConflictoCon($origen, $existentes)) {
                    $omitidas[] = $fecha;
                    $d->addDay();
                    continue;
                }

                foreach ($origen as $tpl) {
                    $nuevo = PiscinaTurno::firstOrCreate(
                        [
                            'campo_id'    => $campoId,
                            'fecha'       => $fecha,
                            'hora_inicio' => $tpl->hora_inicio,
                            'hora_fin'    => $tpl->hora_fin,
                        ],
                        [
                            'cupo_total'  => $tpl->cupo_total,
                            'estado'      => 'activo',
                            'observacion' => $tpl->observacion,
                        ]
                    );

                    // Solo si el turno es recién creado: así un re-lote sobre una
                    // fecha ya clonada NO pisa reservado (idempotencia fuerte).
                    if ($nuevo->wasRecentlyCreated) {
                        $creados++;
                        foreach ($tpl->perfiles as $pf) {
                            $nuevo->perfiles()->create([
                                'perfil'          => $pf->perfil,
                                'cupo'            => $pf->cupo,
                                'reservado'       => 0,
                                'tarifa_sireb_id' => $pf->tarifa_sireb_id,
                                'precio_unitario' => $pf->precio_unitario,
                                'estado'          => 'activo',
                            ]);
                        }
                    }
                }

                $d->addDay();
            }
        });

        return compact('creados', 'omitidas', 'procesadas');
    }

    /**
     * ¿Un intervalo nuevo solapa con algún turno activo de esa campo+fecha?
     * Público: lo consume también el store individual (alineación con cancha).
     * Idéntico (mismo inicio y fin) NO cuenta como conflicto → permite re-uso.
     */
    public function haySolapeEnFecha(string $campoId, string $fecha, string $ini, string $fin): bool
    {
        $existentes = PiscinaTurno::where('campo_id', $campoId)
            ->where('fecha', $fecha)
            ->where('estado', 'activo')
            ->get();

        return $this->hayConflictoCon(
            collect([['hora_inicio' => $ini, 'hora_fin' => $fin]]),
            $existentes
        );
    }

    /** @param Collection<int,object>|array<int,array{hora_inicio:string,hora_fin:string}> $a */
    private function hayConflictoCon($a, Collection $b): bool
    {
        foreach ($a as $t1) {
            foreach ($b as $t2) {
                if ($this->intervalosSolapan($t1->hora_inicio ?? $t1['hora_inicio'], $t1->hora_fin ?? $t1['hora_fin'], (string) $t2->hora_inicio, (string) $t2->hora_fin)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function assertSinSolapeInterno(Collection $turnos): void
    {
        $arr = $turnos->values()->all();
        $n = count($arr);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($this->intervalosSolapan((string) $arr[$i]->hora_inicio, (string) $arr[$i]->hora_fin, (string) $arr[$j]->hora_inicio, (string) $arr[$j]->hora_fin)) {
                    throw new InvalidArgumentException(
                        'El día de referencia tiene turnos solapados entre sí; corregilo antes de clonar.'
                    );
                }
            }
        }
    }

    /** Solape de rango medio-abierto [ini,fin). Idénticos => false (idempotente). */
    private function intervalosSolapan(string $aIni, string $aFin, string $bIni, string $bFin): bool
    {
        $ai = $this->aMinutos($aIni);
        $af = $this->aMinutos($aFin);
        $bi = $this->aMinutos($bIni);
        $bf = $this->aMinutos($bFin);

        if ($ai === $bi && $af === $bf) {
            return false;
        }

        return max($ai, $bi) < min($af, $bf);
    }

    private function aMinutos(string $h): int
    {
        $p = array_map('intval', explode(':', $h));
        return ($p[0] ?? 0) * 60 + ($p[1] ?? 0);
    }
}

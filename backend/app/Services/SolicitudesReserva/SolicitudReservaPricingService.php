<?php

namespace App\Services\SolicitudesReserva;

use App\DTOs\FranjaSolicitadaDTO;
use App\DTOs\ServicioAdicionalDTO;
use App\Models\ArancelVariante;
use App\Models\Asociacion;
use App\Models\TarifaCampo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Resolución de precio/variante/tarifa SIREB para solicitudes de reserva.
 *
 * Extraído de SolicitudReservaService (PR #58). No toca persistencia ni
 * cobro: solo decide qué tarifa se aplica a cada franja/servicio antes
 * de que el orquestador arme la transacción.
 */
class SolicitudReservaPricingService
{
    /**
     * P8.2B: resuelve variante/tarifa/cantidad para cada franja antes de persistir.
     *
     * @param  FranjaSolicitadaDTO[]  $franjas
     * @return array<int, array<string, mixed>>
     */
    public function resolverFranjasParaCrear(
        array $franjas,
        array $campos,
        bool $esAdmin,
        bool $puedeUsarNoVisible = false,
        ?Asociacion $convenioAsociacion = null,
    ): array {
        $resueltas = [];

        foreach ($franjas as $franja) {
            $resuelta = $this->resolverTarifaParaFranja(
                $franja,
                $campos[$franja->campoId],
                $esAdmin,
                $puedeUsarNoVisible,
                $convenioAsociacion
            );

            $resuelta['franja'] = $franja;
            $resuelta['subtotal'] = (float) $resuelta['precio_unitario'] * (int) $resuelta['cantidad'];

            $resueltas[] = $resuelta;
        }

        return $resueltas;
    }

    /**
     * P8.4B: hora de inicio más temprana por campo, para resolver
     * servicios con tarifa diurna/nocturna (ej. escenario) según cuándo
     * se usa el espacio.
     *
     * @param  array<int, array<string, mixed>>  $franjasResueltas
     * @return array<string, int>
     */
    public function calcularHorasPorCampo(array $franjasResueltas): array
    {
        $horasPorCampo = [];

        foreach ($franjasResueltas as $r) {
            $cid = $r['franja']->campoId;
            $seg = self::aSegundos($r['franja']->horaInicio);

            if (! isset($horasPorCampo[$cid]) || $seg < $horasPorCampo[$cid]) {
                $horasPorCampo[$cid] = $seg;
            }
        }

        return $horasPorCampo;
    }

    /**
     * P8.3B: resuelve servicios adicionales antes de persistir.
     *
     * @param  ServicioAdicionalDTO[]  $servicios
     * @return array<int, array<string, mixed>>
     */
    public function resolverServiciosParaCrear(
        array $servicios,
        array $campos,
        bool $esAdmin,
        array $horasPorCampo = [],
        bool $puedeUsarNoVisible = false,
    ): array {
        $resueltos = [];

        foreach ($servicios as $servicio) {
            if (! isset($campos[$servicio->campoId])) {
                throw ValidationException::withMessages([
                    'servicios' => 'Los servicios adicionales deben corresponder a un campo incluido en la reserva.',
                ]);
            }

            $campoInfo = $campos[$servicio->campoId];

            $variante = $campoInfo['variantes']->get($servicio->varianteId);

            if (! $variante) {
                throw ValidationException::withMessages([
                    'servicios' => "La variante del servicio adicional no pertenece al campo {$campoInfo['campo']->nombre}.",
                ]);
            }

            if ($variante->unidad_medida === 'hora') {
                throw ValidationException::withMessages([
                    'servicios' => "El servicio {$variante->nombre} no puede agregarse como servicio adicional porque es una variante horaria.",
                ]);
            }

            if (! $esAdmin && ! $puedeUsarNoVisible && ! $variante->visible_publico) {
                throw ValidationException::withMessages([
                    'servicios' => "El servicio {$variante->nombre} solo está disponible en ventanilla.",
                ]);
            }

            if (blank($variante->servicio_sireb_id)) {
                throw ValidationException::withMessages([
                    'servicios' => "El servicio {$variante->nombre} no está vinculado a SIREB. Ejecutá la sincronización.",
                ]);
            }

            $tarifas = TarifaCampo::query()
                ->where('campo_id', $variante->campo_id)
                ->where('servicio_sireb_id', $variante->servicio_sireb_id)
                ->whereNull('vigente_hasta')
                ->where('liquidable', true)
                ->where('tarifario_estado', 'vigente')
                ->get();

            if ($tarifas->isEmpty()) {
                throw ValidationException::withMessages([
                    'servicios' => "No hay tarifa SIREB vigente espejada para el servicio {$variante->nombre}.",
                ]);
            }

            $unica = $tarifas->firstWhere('clasificacion', 'unica');

            if ($unica) {
                $tarifa = $unica;
            } elseif ($tarifas->count() === 1) {
                $tarifa = $tarifas->first();
            } else {
                $horaSeg = $horasPorCampo[$servicio->campoId] ?? null;

                if ($horaSeg === null) {
                    throw ValidationException::withMessages([
                        'servicios' => "El servicio {$variante->nombre} se cobra por tramo (diurno/nocturno) y no hay franja horaria en el mismo campo para resolverlo.",
                    ]);
                }

                $tipo = $horaSeg < $campoInfo['inicio_noche'] ? 'diurna' : 'nocturna';
                $tarifa = $tarifas->firstWhere('clasificacion', $tipo);

                if (! $tarifa) {
                    throw ValidationException::withMessages([
                        'servicios' => "No hay tarifa {$tipo} vigente espejada para el servicio {$variante->nombre}.",
                    ]);
                }
            }

            if (blank($tarifa->tarifa_sireb_id)) {
                throw ValidationException::withMessages([
                    'servicios' => "El servicio {$variante->nombre} no tiene tarifa SIREB espejada. Ejecutá la sincronización.",
                ]);
            }

            $cantidad = max(1, (int) $servicio->cantidad);
            $precioUnitario = (float) $tarifa->precio_por_hora;
            $subtotal = round($precioUnitario * $cantidad, 2);

            $resueltos[] = [
                'servicio' => $servicio,
                'campo_id' => $variante->campo_id,
                'variante_id' => $variante->id,
                'servicio_sireb_id' => $variante->servicio_sireb_id,
                'tarifa_sireb_id' => $tarifa->tarifa_sireb_id,
                'etiqueta_sireb' => $tarifa->etiqueta_sireb,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal' => $subtotal,
            ];
        }

        return $resueltos;
    }

    /**
     * Convierte "HH:MM:SS" a segundos desde medianoche.
     */
    public static function aSegundos(string $hora): int
    {
        $partes = array_map('intval', explode(':', $hora));

        return ($partes[0] * 3600) + ($partes[1] * 60) + ($partes[2] ?? 0);
    }

    /**
     * Resuelve la tarifa SIREB aplicable a una franja.
     *
     * @return array{
     *     variante_id: ?string,
     *     servicio_sireb_id: ?string,
     *     tarifa_sireb_id: string,
     *     etiqueta_sireb: ?string,
     *     cantidad: int,
     *     precio_unitario: float
     * }
     */
    private function resolverTarifaParaFranja(
        FranjaSolicitadaDTO $franja,
        array $campoInfo,
        bool $esAdmin,
        bool $puedeUsarNoVisible = false,
        ?Asociacion $convenioAsociacion = null,
    ): array {
        $variante = null;
        $tarifas = $campoInfo['tarifas'];

        if (filled($franja->varianteId)) {
            $variante = $campoInfo['variantes']->get($franja->varianteId);

            if (! $variante) {
                throw ValidationException::withMessages([
                    'franjas' => "La variante seleccionada no pertenece al campo {$campoInfo['campo']->nombre}.",
                ]);
            }

            if (! $esAdmin && ! $puedeUsarNoVisible && ! $variante->visible_publico) {
                throw ValidationException::withMessages([
                    'franjas' => "La variante {$variante->nombre} solo está disponible en ventanilla.",
                ]);
            }

            if ($variante->unidad_medida !== 'hora') {
                throw ValidationException::withMessages([
                    'franjas' => "La variante {$variante->nombre} no es reservable por franja horaria en esta etapa.",
                ]);
            }

            if (blank($variante->servicio_sireb_id)) {
                throw ValidationException::withMessages([
                    'franjas' => "La variante {$variante->nombre} no está vinculada a SIREB. Ejecutá la sincronización.",
                ]);
            }

            $tarifas = $tarifas->where('servicio_sireb_id', $variante->servicio_sireb_id);
        } else {
            $autoVariante = $this->autoResolverVarianteConvenio(
                $campoInfo['variantes'],
                $convenioAsociacion,
            );

            if ($autoVariante !== null) {
                $variante = $autoVariante;
                $tarifas = $tarifas->where('servicio_sireb_id', $variante->servicio_sireb_id);
            } else {
                if ($campoInfo['variantes']->isNotEmpty() && blank($campoInfo['campo']->servicio_sireb_id)) {
                    throw ValidationException::withMessages([
                        'franjas' => "El campo {$campoInfo['campo']->nombre} requiere seleccionar una variante arancelaria.",
                    ]);
                }

                if (filled($campoInfo['campo']->servicio_sireb_id)) {
                    $filtradas = $tarifas->where('servicio_sireb_id', $campoInfo['campo']->servicio_sireb_id);

                    if ($filtradas->isNotEmpty()) {
                        $tarifas = $filtradas;
                    }
                } elseif ($tarifas->whereNotNull('servicio_sireb_id')->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'franjas' => "El campo {$campoInfo['campo']->nombre} no está vinculado a SIREB pero tiene tarifas espejadas de un servicio. Vinculalo o ejecutá la sincronización.",
                    ]);
                }
            }
        }

        if ($tarifas->isEmpty()) {
            throw ValidationException::withMessages([
                'franjas' => "No hay tarifa SIREB vigente espejada para la selección del campo {$campoInfo['campo']->nombre}.",
            ]);
        }

        $unica = $tarifas->firstWhere('clasificacion', 'unica');

        if ($unica) {
            $tarifa = $unica;
        } else {
            $tipo = self::aSegundos($franja->horaInicio) < $campoInfo['inicio_noche']
                ? 'diurna'
                : 'nocturna';

            $tarifa = $tarifas->firstWhere('clasificacion', $tipo);

            if (! $tarifa) {
                throw ValidationException::withMessages([
                    'franjas' => "No hay tarifa {$tipo} vigente espejada para la franja solicitada.",
                ]);
            }
        }

        if ($variante !== null && blank($tarifa->tarifa_sireb_id)) {
            throw ValidationException::withMessages([
                'franjas' => "La variante {$variante->nombre} no tiene tarifa SIREB espejada. Ejecutá la sincronización.",
            ]);
        }

        return [
            'variante_id' => $variante?->id,
            'servicio_sireb_id' => $tarifa->servicio_sireb_id ?? $variante?->servicio_sireb_id,
            'tarifa_sireb_id' => $tarifa->tarifa_sireb_id,
            'etiqueta_sireb' => $tarifa->etiqueta_sireb,
            'cantidad' => 1,
            'precio_unitario' => (float) $tarifa->precio_por_hora,
        ];
    }

    /**
     * [Fase 6b] Elige la única variante de convenio que le corresponde a una
     * asociación logueada cuando el front no mandó variante_id explícito.
     */
    private function autoResolverVarianteConvenio(
        Collection $variantes,
        ?Asociacion $convenioAsociacion,
    ): ?ArancelVariante {
        if ($convenioAsociacion === null) {
            return null;
        }

        $tipoAsoc = $convenioAsociacion->tipo?->value;
        if (blank($tipoAsoc)) {
            return null;
        }

        $candidatas = $variantes->filter(
            fn (ArancelVariante $v) => $v->activo
                && $v->unidad_medida === 'hora'
                && $v->tipo_asociacion === $tipoAsoc
                && filled($v->servicio_sireb_id)
        );

        return $candidatas->count() === 1 ? $candidatas->first() : null;
    }
}

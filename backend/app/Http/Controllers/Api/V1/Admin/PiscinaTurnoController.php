<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CampoDeportivo;
use App\Models\PiscinaTurno;
use App\Models\PiscinaTurnoPerfil;
use App\Services\Piscina\PiscinaTarifaResolver;
use App\Services\Piscina\PiscinaTurnoLoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Controller admin para gestión de turnos de piscina.
 */
class PiscinaTurnoController extends Controller
{
    public function __construct(
        private PiscinaTurnoLoteService $lote,
        private PiscinaTarifaResolver $tarifaResolver,
    ) {
    }

    /**
     * GET /api/v1/admin/piscina/campos/{campoId}/turnos
     */
    public function index(Request $request, string $campoId): JsonResponse
    {
        $campo = CampoDeportivo::find($campoId);
        if (!$campo) {
            return response()->json(['message' => 'Campo no encontrado'], 404);
        }

        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');

        $query = PiscinaTurno::where('campo_id', $campoId)
            ->with('perfiles')
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        if ($fechaDesde) {
            $query->where('fecha', '>=', $fechaDesde);
        }
        if ($fechaHasta) {
            $query->where('fecha', '<=', $fechaHasta);
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * POST /api/v1/admin/piscina/turnos
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campo_id' => ['required', 'uuid', 'exists:campos_deportivos,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i:s'],
            'hora_fin' => ['required', 'date_format:H:i:s', 'after:hora_inicio'],
            'cupo_total' => ['nullable', 'integer', 'min:1'],
            // ⟦FIX 1⟧ era 'in:activo,activo' (typo) → nunca se podía inactivar.
            'estado' => ['required', 'in:activo,inactivo'],
            'observacion' => ['nullable', 'string'],
            'perfiles' => ['required', 'array', 'min:1'],
            // [dedupe] un perfil no puede repetirse en el mismo turno (2 filas mayor_12
            // colapsan el pluck('reservado','perfil') del update y duplican precio).
            'perfiles.*.perfil' => ['required', 'in:mayor_12,menor_12,institucion', 'distinct'],
            'perfiles.*.cupo' => ['required', 'integer', 'min:1'],
            // [B99] precio_unitario y tarifa_sireb_id NO vienen del payload: los
            // resuelve SIREB vía PiscinaTarifaResolver (misma fuente que disponibilidad
            // y que el gate B70-store). El funcionario no inventa el precio.
        ]);

        $campo = CampoDeportivo::find($validated['campo_id']);
        if ($campo->modalidad_reserva?->value !== 'turno_por_perfil') {
            return response()->json([
                'message' => 'Este campo no está configurado como piscina',
            ], 422);
        }

        // [B70-store + B99] un solo paso: verifica que cada perfil tenga tarifa SIREB
        // vigente (invariante "reservable ⟺ tarifa espejada") Y resuelve el precio +
        // tarifa_sireb_id desde esa misma tarifa. Si algún perfil no tiene tarifa,
        // respondo 422 ANTES de persistir (fail-fast, patrón del gate de solape).
        $res = $this->resolverTarifasPerfiles(
            $validated['campo_id'],
            $validated['hora_inicio'],
            $validated['perfiles'],
        );
        if ($res['sinTarifa'] !== []) {
            return response()->json([
                'message' => 'Uno o más perfiles no tienen tarifa SIREB vigente. Sincronizá tarifas (sireb:sincronizar-tarifas) o verificá el vínculo del campo antes de crear el turno.',
                'perfiles_sin_tarifa' => $res['sinTarifa'],
            ], 422);
        }

        // ⟦Gate#1 alineado con cancha⟧ piscina no tiene EXCLUDE de rango en BD,
        // así que el solape se corta acá en PHP (mismo criterio que el lote).
        if ($this->lote->haySolapeEnFecha($validated['campo_id'], $validated['fecha'], $validated['hora_inicio'], $validated['hora_fin'])) {
            return response()->json([
                'message' => 'Ya existe un turno activo que se solapa con ese horario en esa fecha.',
            ], 422);
        }

        try {
            $turno = DB::transaction(function () use ($validated, $res) {
                $turno = PiscinaTurno::create([
                    'campo_id' => $validated['campo_id'],
                    'fecha' => $validated['fecha'],
                    'hora_inicio' => $validated['hora_inicio'],
                    'hora_fin' => $validated['hora_fin'],
                    'cupo_total' => $validated['cupo_total'],
                    'estado' => $validated['estado'],
                    'observacion' => $validated['observacion'],
                ]);

                // [B99] guardo el precio/tarifa_sireb_id RESUELTOS por SIREB, no los
                // que haya tipeado el funcionario (el payload ya no los trae).
                foreach ($res['resueltos'] as $perfilResuelto) {
                    $turno->perfiles()->create([
                        'perfil' => $perfilResuelto['perfil'],
                        'cupo' => $perfilResuelto['cupo'],
                        'reservado' => 0,
                        'tarifa_sireb_id' => $perfilResuelto['tarifa_sireb_id'],
                        'precio_unitario' => $perfilResuelto['precio_unitario'],
                        'estado' => 'activo',
                    ]);
                }

                return $turno->load('perfiles');
            });

            return response()->json(['message' => 'Turno creado exitosamente', 'data' => $turno], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear turno', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/admin/piscina/turnos/lote
     *
     * Clona los turnos activos de un día referencia a un rango de fechas.
     */
    public function storeLote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campo_id' => ['required', 'uuid', 'exists:campos_deportivos,id'],
            'fecha_origen' => ['required', 'date'],
            'desde' => ['required', 'date', 'after_or_equal:today'],
            'hasta' => [
                'required', 'date', 'after_or_equal:desde',
                'before_or_equal:' . now()->addDays(60)->toDateString(),
            ],
        ]);

        $campo = CampoDeportivo::find($validated['campo_id']);
        if ($campo->modalidad_reserva?->value !== 'turno_por_perfil') {
            return response()->json(['message' => 'Este campo no está configurado como piscina'], 422);
        }

        try {
            $res = $this->lote->clonarDesdeReferencia(
                $validated['campo_id'],
                $validated['fecha_origen'],
                $validated['desde'],
                $validated['hasta'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al generar el lote', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => "Lote procesado: {$res['creados']} turnos creados, " . count($res['omitidas']) . " fechas omitidas por solape.",
            'data' => $res,
        ], 201);
    }

    /**
     * PATCH /api/v1/admin/piscina/turnos/{turnoId}
     * (el intervalo fecha/hora es inmutable acá → no genera solape nuevo;
     *  la edición de perfiles PRESERVA reservado, Fix3-real — ya no es gate PO)
     */
    public function update(Request $request, string $turnoId): JsonResponse
    {
        $turno = PiscinaTurno::find($turnoId);
        if (!$turno) {
            return response()->json(['message' => 'Turno no encontrado'], 404);
        }

        $validated = $request->validate([
            'estado' => ['sometimes', 'in:activo,inactivo'], // ⟦FIX 1⟧
            'observacion' => ['nullable', 'string'],
            'perfiles' => ['sometimes', 'array'],
            'perfiles.*.perfil' => ['required', 'in:mayor_12,menor_12,institucion', 'distinct'],
            'perfiles.*.cupo' => ['required', 'integer', 'min:1'],
            // [B99] idem store: precio/tarifa_sireb_id los resuelve SIREB, no el payload.
        ]);

        // [Fix3-real] reservado es contador FÍSICO (PiscinaReservaService lo incrementa al
        // crear y lo decrementa con min(cantidad,reservado) al cancelar; NO es derivable de
        // un SUM de detalles porque el estado vive en la cabecera, FIX 4b). El update
        // anterior lo reseteaba a 0 → sobreventa. Validamos ANTES de la transacción
        // (fail-fast, patrón cancelar()/B70-store): ningún cupo nuevo baja de lo ya tomado
        // y ningún perfil con gente reservada se quita. Capturamos el reservado por perfil
        // pa' preservarlo en el recreate de abajo.
        $reservadoActual = [];
        $res = ['sinTarifa' => [], 'resueltos' => []];
        if (isset($validated['perfiles'])) {
            $reservadoActual = $turno->perfiles()->pluck('reservado', 'perfil')->all();
            $nuevosPerfiles = [];
            foreach ($validated['perfiles'] as $perfilData) {
                $nuevosPerfiles[] = $perfilData['perfil'];
                $tomado = (int) ($reservadoActual[$perfilData['perfil']] ?? 0);
                if ((int) $perfilData['cupo'] < $tomado) {
                    return response()->json([
                        'message' => "El cupo de {$perfilData['perfil']} no puede ser menor a {$tomado} (ya reservado).",
                    ], 422);
                }
            }
            foreach ($reservadoActual as $perfil => $tomado) {
                if (! in_array($perfil, $nuevosPerfiles, true) && (int) $tomado > 0) {
                    return response()->json([
                        'message' => "No se puede quitar el perfil {$perfil}: tiene {$tomado} reservado(s).",
                    ], 422);
                }
            }

            // [B99] la hora es inmutable → resuelvo contra $turno->hora_inicio (no del
            // payload). Si un perfil perdió su tarifa SIREB (sync la versionó), 422:
            // no guardo un turno con perfil sin precio espejado.
            $res = $this->resolverTarifasPerfiles(
                $turno->campo_id,
                $turno->hora_inicio,
                $validated['perfiles'],
            );
            if ($res['sinTarifa'] !== []) {
                return response()->json([
                    'message' => 'Uno o más perfiles no tienen tarifa SIREB vigente. Sincronizá tarifas antes de guardar.',
                    'perfiles_sin_tarifa' => $res['sinTarifa'],
                ], 422);
            }
        }

        try {
            DB::transaction(function () use ($turno, $validated, $reservadoActual, $res) {
                if (isset($validated['estado'])) {
                    $turno->estado = $validated['estado'];
                }
                if (isset($validated['observacion'])) {
                    $turno->observacion = $validated['observacion'];
                }
                $turno->save();

                if (isset($validated['perfiles'])) {
                    $turno->perfiles()->delete();
                    foreach ($res['resueltos'] as $perfilResuelto) {
                        $turno->perfiles()->create([
                            'perfil' => $perfilResuelto['perfil'],
                            'cupo' => $perfilResuelto['cupo'],
                            // [Fix3-real] preservamos el cupo tomado; el delete+recreate
                            // ya no lo pisa a 0.
                            'reservado' => (int) ($reservadoActual[$perfilResuelto['perfil']] ?? 0),
                            // [B99] precio/tarifa_sireb_id espejados de SIREB.
                            'tarifa_sireb_id' => $perfilResuelto['tarifa_sireb_id'],
                            'precio_unitario' => $perfilResuelto['precio_unitario'],
                            'estado' => 'activo',
                        ]);
                    }
                }
            });

            return response()->json(['message' => 'Turno actualizado exitosamente', 'data' => $turno->load('perfiles')]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar turno', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/v1/admin/piscina/turnos/{turnoId}
     */
    public function destroy(string $turnoId): JsonResponse
    {
        $turno = PiscinaTurno::find($turnoId);
        if (!$turno) {
            return response()->json(['message' => 'Turno no encontrado'], 404);
        }

        // ⟦FIX 4b⟧ el detalle NO tiene columna 'estado'; se filtra por cabecera.
        $hayPendientes = $turno->reservas()
            ->whereHas('reserva', fn ($q) => $q->where('estado', 'pendiente'))
            ->exists();

        if ($hayPendientes) {
            return response()->json([
                'message' => 'No se puede eliminar: el turno tiene reservas pendientes',
            ], 422);
        }

        try {
            $turno->delete();
            return response()->json(['message' => 'Turno eliminado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar turno', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * [B99] El precio del perfil NO lo tipea el funcionario: lo resuelve SIREB vía
     * PiscinaTarifaResolver — la MISMA fuente que lee disponibilidad (y que el gate
     * B70-store ya consultaba solo pa' existencia). Retorna, por perfil del payload:
     * el cupo (decisión del funcionario) + precio_unitario y tarifa_sireb_id espejados
     * de la tarifa vigente. Si un perfil no tiene tarifa, va a sinTarifa (caller 422).
     *
     * Cierra B99 en la fuente: el dato almacenado ya es SIREB, así que 147/391/489
     * (público) leen lo mismo sin parches (S49).
     *
     * @return array{sinTarifa: string[], resueltos: array<int, array{perfil: string, cupo: int, tarifa_sireb_id: string|null, precio_unitario: float}>}
     */
    private function resolverTarifasPerfiles(string $campoId, string $horaInicio, array $perfiles): array
    {
        $sinTarifa = [];
        $resueltos = [];

        foreach ($perfiles as $perfilData) {
            $tarifa = $this->tarifaResolver->resolver(
                $campoId,
                $perfilData['perfil'],
                $horaInicio,
            );

            if (! $tarifa) {
                $sinTarifa[] = $perfilData['perfil'];
                continue;
            }

            $resueltos[] = [
                'perfil' => $perfilData['perfil'],
                'cupo' => (int) $perfilData['cupo'],
                'tarifa_sireb_id' => $tarifa->tarifa_sireb_id,
                'precio_unitario' => (float) $tarifa->precio_por_hora,
            ];
        }

        return ['sinTarifa' => $sinTarifa, 'resueltos' => $resueltos];
    }

    /**
     * [B99-precio] GET /v1/admin/piscina/campos/{campoId}/tarifas-perfil?hora_inicio=HH:MM:SS
     *
     * Devuelve el precio SIREB resuelto por perfil para la hora dada, usando el MISMO
     * PiscinaTarifaResolver que availability y que el gate de store/update. Así el front
     * muestra el precio sin duplicar la regla diurna/nocturna (fuente única = resolver).
     * Perfil sin tarifa vigente → precio_unitario null (el front avisa "sin tarifa"; el
     * store igual falla 422 al guardar, B70-store). No valida existencia de campo: el
     * resolver ya tolera campo inexistente devolviendo null (endpoint de solo lectura).
     */
    public function tarifasPerfil(Request $request, string $campoId): JsonResponse
    {
        $validated = $request->validate([
            'hora_inicio' => ['required', 'date_format:H:i:s'],
        ]);

        $precios = [];
        foreach (['mayor_12', 'menor_12', 'institucion'] as $perfil) {
            $tarifa = $this->tarifaResolver->resolver($campoId, $perfil, $validated['hora_inicio']);
            $precios[] = [
                'perfil' => $perfil,
                'precio_unitario' => $tarifa ? (float) $tarifa->precio_por_hora : null,
                'tarifa_sireb_id' => $tarifa?->tarifa_sireb_id,
            ];
        }

        return response()->json(['data' => $precios]);
    }
}

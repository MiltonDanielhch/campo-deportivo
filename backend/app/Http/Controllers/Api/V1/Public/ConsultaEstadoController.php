<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\SolicitudEstadoResource;
use App\Integrations\Recaudaciones\RecaudacionesApiClientInterface;
use App\Models\PiscinaReserva;
use App\Models\SolicitudReserva;
use App\Services\ConfirmacionCobroService;
use App\Services\Piscina\PiscinaConfirmacionCobroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Consulta ciudadana unificada (campos + piscina).
 *
 * Acepta como término de búsqueda:
 *   - código de seguimiento (RES-… / PIS-…)
 *   - código de pago en ventanilla de SIREB (referencia_recaudaciones, p.ej. K699-88PZ-J7G)
 *   - CI/NIT o teléfono del pagador (devuelve la reserva más reciente)
 *
 * Reutiliza la verificación proactiva contra SIREB (igual que SolicitudEstadoController)
 * para que, si el ciudadano ya pagó, la reserva se confirme antes de responder.
 */
class ConsultaEstadoController extends Controller
{
    public function __construct(
        private ConfirmacionCobroService $confirmacionCampo,
        private PiscinaConfirmacionCobroService $confirmacionPiscina,
        private RecaudacionesApiClientInterface $sirebClient,
    ) {
    }

    public function show(string $termino): JsonResponse
    {
        $termino = strtoupper(trim($termino));

        if ($termino === '') {
            return response()->json(['message' => 'Ingresá un código, CI/NIT o teléfono.'], 422);
        }

        // ── Piscina: código de seguimiento (PIS-…) o de ventanilla (K699-…) ──
        $piscina = PiscinaReserva::with(['detalles.turno', 'campo'])
            ->where('codigo_seguimiento', $termino)
            ->orWhere('referencia_recaudaciones', $termino)
            ->first();

        if ($piscina) {
            return $this->respuestaPiscina($piscina);
        }

        // ── Campos: código de seguimiento (RES-…) o de ventanilla ──
        $solicitud = SolicitudReserva::with('reservas.campo')
            ->where('codigo_seguimiento', $termino)
            ->orWhere('referencia_recaudaciones', $termino)
            ->first();

        if ($solicitud) {
            return $this->respuestaCampo($solicitud);
        }

        // ── CI/NIT o teléfono del pagador (más reciente) ──
        $piscina = PiscinaReserva::with(['detalles.turno', 'campo'])
            ->where('ci_nit_pagador', $termino)
            ->orWhere('telefono_pagador', $termino)
            ->orderByDesc('created_at')
            ->first();

        if ($piscina) {
            return $this->respuestaPiscina($piscina);
        }

        $solicitud = SolicitudReserva::with('reservas.campo')
            ->where('ci_nit_pagador', $termino)
            ->orWhere('telefono_pagador', $termino)
            ->orderByDesc('creado_en')
            ->first();

        if ($solicitud) {
            return $this->respuestaCampo($solicitud);
        }

        return response()->json(['message' => 'No encontramos ninguna reserva con ese código.'], 404);
    }

    private function respuestaCampo(SolicitudReserva $solicitud): JsonResponse
    {
        $this->verificarSirebCampo($solicitud);

        return response()->json([
            'data' => array_merge(
                ['tipo' => 'campo'],
                (new SolicitudEstadoResource($solicitud))->resolve(request()),
            ),
        ]);
    }

    private function respuestaPiscina(PiscinaReserva $reserva): JsonResponse
    {
        $this->verificarSirebPiscina($reserva);
        $reserva->refresh();

        return response()->json([
            'data' => $this->payloadPiscina($reserva),
        ]);
    }

    private function verificarSirebCampo(SolicitudReserva $solicitud): void
    {
        if ($solicitud->estado->value !== 'pendiente' || ! $solicitud->referencia_recaudaciones) {
            return;
        }

        try {
            $estado = $this->sirebClient->consultarLiquidacionPorCodigo($solicitud->referencia_recaudaciones);

            if ($estado && ($estado['estado'] ?? '') === 'pagada') {
                $this->confirmacionCampo->confirmar($solicitud, $estado['monto'] ?? null);
                $solicitud->refresh();
            }
        } catch (\Exception $e) {
            Log::channel('sireb')->warning('Error al verificar estado SIREB en consulta ciudadana (campo)', [
                'codigo_seguimiento' => $solicitud->codigo_seguimiento,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function verificarSirebPiscina(PiscinaReserva $reserva): void
    {
        if ($reserva->estado->value !== 'pendiente' || ! $reserva->referencia_recaudaciones) {
            return;
        }

        try {
            $estado = $this->sirebClient->consultarLiquidacionPorCodigo($reserva->referencia_recaudaciones);

            if ($estado && ($estado['estado'] ?? '') === 'pagada') {
                $this->confirmacionPiscina->confirmar($reserva, $estado['monto'] ?? null);
            }
        } catch (\Exception $e) {
            Log::channel('sireb')->warning('Error al verificar estado SIREB en consulta ciudadana (piscina)', [
                'codigo_seguimiento' => $reserva->codigo_seguimiento,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normaliza una reserva de piscina al mismo contrato que consume el frontend
     * público (EstadoSolicitudPublico), reutilizando sus componentes de comprobante.
     */
    private function payloadPiscina(PiscinaReserva $r): array
    {
        $pendienteConRef = $r->estado->value === 'pendiente' && $r->referencia_recaudaciones;

        return [
            'tipo' => 'piscina',
            'codigo_seguimiento' => $r->codigo_seguimiento,
            'estado' => $r->estado->value,
            'monto_total' => (float) $r->monto_total,
            // [3b] monto_confirmado: lo que SIREB reportó al confirmar (autoritativo
            // post-pago, KB SIREB). Null en confirmadas viejas (la migración
            // add_monto_confirmado lo agregó nullable) -> el front cae a monto_total.
            'monto_confirmado' => $r->monto_confirmado !== null ? (float) $r->monto_confirmado : null,
            'expira_en' => $r->expira_en?->toIso8601String(),
            'referencia_recaudaciones' => $r->referencia_recaudaciones,

            // SIREB v1: el QR se reconstruye desde el código público.
            'datos_cobro_pendiente' => $pendienteConRef ? [
                'qr_string' => 'SIREB:'.$r->referencia_recaudaciones,
                'codigo_publico' => $r->referencia_recaudaciones,
                'checkout_url' => null,
            ] : null,

            'reservas' => $r->estado->value === 'confirmada'
                ? $r->detalles->map(fn ($d) => [
                    'codigo_reserva' => $r->codigo_seguimiento,
                    'campo_nombre' => $r->campo?->nombre,
                    'fecha' => $d->fecha?->format('Y-m-d'),
                    'hora_inicio' => $d->hora_inicio?->format('H:i'),
                    'hora_fin' => $d->hora_fin?->format('H:i'),
                    'confirmado_en' => $r->updated_at?->toIso8601String(),
                    'perfil' => $d->perfil,
                    'cantidad' => $d->cantidad,
                ])->all()
                : null,
        ];
    }
}
